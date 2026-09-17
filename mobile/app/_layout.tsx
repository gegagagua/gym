import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import * as SplashScreen from 'expo-splash-screen';
import * as SystemUI from 'expo-system-ui';
import NetInfo from '@react-native-community/netinfo';
import { useFonts } from 'expo-font';
import { Unbounded_700Bold, Unbounded_900Black } from '@expo-google-fonts/unbounded';
import {
  NotoSansGeorgian_400Regular,
  NotoSansGeorgian_500Medium,
  NotoSansGeorgian_700Bold,
} from '@expo-google-fonts/noto-sans-georgian';

import '@/i18n';
import { colors } from '@/theme';
import { initDatabase } from '@/db';
import { flushQueue, pruneSynced } from '@/db/sync';
import { useAuth } from '@/store/auth';
import { useSettings } from '@/store/settings';

SplashScreen.preventAutoHideAsync().catch(() => undefined);
SystemUI.setBackgroundColorAsync(colors.bg).catch(() => undefined);

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // ვარჯიში ხშირად სუსტ ქსელშია — ხელახალი ცდები აგრესიული არ უნდა იყოს
      retry: 1,
      staleTime: 60_000,
      refetchOnWindowFocus: false,
    },
  },
});

export default function RootLayout() {
  const [dbReady, setDbReady] = useState(false);
  const { ready, bootstrap } = useAuth();
  const hydrateSettings = useSettings((s) => s.hydrate);

  const [fontsLoaded] = useFonts({
    Unbounded_700Bold,
    Unbounded_900Black,
    NotoSansGeorgian_400Regular,
    NotoSansGeorgian_500Medium,
    NotoSansGeorgian_700Bold,
  });

  useEffect(() => {
    initDatabase();
    setDbReady(true);
    hydrateSettings();
    bootstrap();
    pruneSynced().catch(() => undefined);
  }, [bootstrap, hydrateSettings]);

  // კავშირის აღდგენა → რიგის გაგზავნა (სპეც. 12.3, ნაბიჯი 2)
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      if (state.isConnected && state.isInternetReachable !== false) {
        flushQueue().catch(() => undefined);
      }
    });

    return unsubscribe;
  }, []);

  useEffect(() => {
    if (fontsLoaded && ready && dbReady) SplashScreen.hideAsync().catch(() => undefined);
  }, [fontsLoaded, ready, dbReady]);

  if (!fontsLoaded || !ready || !dbReady) {
    return <View style={{ flex: 1, backgroundColor: colors.bg }} />;
  }

  return (
    <GestureHandlerRootView style={{ flex: 1, backgroundColor: colors.bg }}>
      <SafeAreaProvider>
        <QueryClientProvider client={queryClient}>
          <StatusBar style="light" />
          <AuthGate />
          <Stack
            screenOptions={{
              headerShown: false,
              contentStyle: { backgroundColor: colors.bg },
              animation: 'slide_from_right',
            }}
          >
            <Stack.Screen name="(onboarding)" />
            <Stack.Screen name="auth" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="(tabs)" />
            <Stack.Screen name="player" options={{ animation: 'slide_from_bottom', gestureEnabled: false }} />
            <Stack.Screen name="summary" options={{ animation: 'fade', gestureEnabled: false }} />
            <Stack.Screen name="programs" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="exercise/[id]" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="spot/[id]" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="spot/new" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="plan/index" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="plan/setup" />
            <Stack.Screen name="paywall" options={{ animation: 'slide_from_bottom' }} />
            <Stack.Screen name="kegel" options={{ animation: 'slide_from_bottom', gestureEnabled: false }} />
          </Stack>
        </QueryClientProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

/** ონბორდინგი ერთხელ; შემდეგ პირდაპირ ტაბებზე */
function AuthGate() {
  const { onboarded, user } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    // ავტორიზაციის ეკრანი ონბორდინგის გარეთაა — გეითმა უკან არ უნდა დააბრუნოს
    const inOnboarding = segments[0] === '(onboarding)';
    const inAuth = segments[0] === 'auth';
    const done = onboarded && user;

    if (!done && !inOnboarding && !inAuth) router.replace('/(onboarding)');
    else if (done && inOnboarding) router.replace('/(tabs)');
  }, [onboarded, user, segments, router]);

  return null;
}
