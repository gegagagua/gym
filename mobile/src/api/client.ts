import Constants from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import i18n from '@/i18n';

const TOKEN_KEY = 'kalisteni.token';

export const API_URL =
  (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl ??
  'http://localhost:8000/api/v1';

let memoryToken: string | null = null;

export async function getToken(): Promise<string | null> {
  if (memoryToken) return memoryToken;
  memoryToken = await SecureStore.getItemAsync(TOKEN_KEY);
  return memoryToken;
}

export async function setToken(token: string | null) {
  memoryToken = token;
  if (token) await SecureStore.setItemAsync(TOKEN_KEY, token);
  else await SecureStore.deleteItemAsync(TOKEN_KEY);
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public payload: any,
  ) {
    super(payload?.message ?? `HTTP ${status}`);
    this.name = 'ApiError';
  }

  /** ვალიდაციის შეცდომები ველების მიხედვით */
  get errors(): Record<string, string[]> {
    return this.payload?.errors ?? {};
  }

  get isOffline() {
    return this.status === 0;
  }
}

interface RequestOptions extends Omit<RequestInit, 'body'> {
  body?: unknown;
  query?: Record<string, string | number | boolean | string[] | undefined | null>;
  auth?: boolean;
  timeoutMs?: number;
}

function buildUrl(path: string, query?: RequestOptions['query']) {
  const url = new URL(API_URL + path);

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    if (Array.isArray(value)) value.forEach((v) => url.searchParams.append(`${key}[]`, String(v)));
    else url.searchParams.append(key, String(value));
  });

  return url.toString();
}

export async function api<T = any>(path: string, options: RequestOptions = {}): Promise<T> {
  const { body, query, auth = true, timeoutMs = 15000, headers, ...rest } = options;

  const finalHeaders: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': i18n.language,
    ...(headers as Record<string, string>),
  };

  const isFormData = body instanceof FormData;
  if (body !== undefined && !isFormData) finalHeaders['Content-Type'] = 'application/json';

  if (auth) {
    const token = await getToken();
    if (token) finalHeaders.Authorization = `Bearer ${token}`;
  }

  // ვარჯიში ხშირად სარდაფში ან ეზოშია — მოთხოვნა უსასრულოდ არ უნდა ეკიდოს
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let response: Response;
  try {
    response = await fetch(buildUrl(path, query), {
      ...rest,
      headers: finalHeaders,
      signal: controller.signal,
      body: body === undefined ? undefined : isFormData ? (body as FormData) : JSON.stringify(body),
    });
  } catch (error) {
    throw new ApiError(0, { message: (error as Error).message, offline: true });
  } finally {
    clearTimeout(timer);
  }

  if (response.status === 204) return undefined as T;

  const text = await response.text();
  const payload = text ? safeParse(text) : null;

  if (!response.ok) throw new ApiError(response.status, payload);

  return payload as T;
}

function safeParse(text: string) {
  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}
