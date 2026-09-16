import { useEffect, useRef, useState } from 'react';
import { View, TextInput, Alert } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import { Screen, Text, Button, Card, Tap, Reveal, Fade } from '@/components';
import { colors, easing, radius, space, border } from '@/theme';
import { auth as authApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { useAuth } from '@/store/auth';

type Step = 'phone' | 'code';

/**
 * ტელეფონით შესვლა და სტუმრის ანგარიშის მიბმა.
 *
 *   mode=signin   — არსებულ ანგარიშში დაბრუნება (ახალი მოწყობილობა, logout)
 *   mode=upgrade  — სტუმარს ნომერი ებმება, XP და ისტორია ადგილზე რჩება
 *
 * ლოკალურად SMS პროვაიდერი არ არის — სერვერი კოდს პასუხში აბრუნებს
 * (`debug_code`) და ველი წინასწარ ივსება.
 */
export default function AuthScreen() {
  const { mode } = useLocalSearchParams<{ mode?: 'signin' | 'upgrade' }>();
  const isUpgrade = mode === 'upgrade';

  const { t } = useTranslation();
  const router = useRouter();
  const { signInWithOtp, upgradeGuest, completeOnboarding } = useAuth();

  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('+995');
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [cooldown, setCooldown] = useState(0);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  // შეცდომა ბარათს არხევს — ტექსტამდე უკვე ცხადია, რომ რაღაც არ გამოვიდა
  const shake = useSharedValue(0);
  const shaking = useAnimatedStyle(() => ({ transform: [{ translateX: shake.value }] }));

  const fail = (text: string) => {
    setError(text);
    shake.value = withSequence(
      withTiming(-8, { duration: 55, easing: easing.out }),
      withTiming(8, { duration: 70, easing: easing.inOut }),
      withTiming(-5, { duration: 70, easing: easing.inOut }),
      withTiming(0, { duration: 90, easing: easing.out }),
    );
  };

  useEffect(() => () => (timer.current ? clearInterval(timer.current) : undefined), []);

  const startCooldown = () => {
    setCooldown(60);
    timer.current && clearInterval(timer.current);
    timer.current = setInterval(() => {
      setCooldown((value) => {
        if (value <= 1 && timer.current) clearInterval(timer.current);
        return Math.max(0, value - 1);
      });
    }, 1000);
  };

  const message = (e: unknown) => {
    if (e instanceof ApiError) {
      const first = Object.values(e.errors)[0]?.[0];
      if (first) return first;
      if (e.status === 429) return t('auth.tooMany');
      if (e.isOffline) return t('common.offline');
    }
    return t('common.error');
  };

  const requestCode = async () => {
    setBusy(true);
    setError(null);

    try {
      const response = await authApi.requestOtp(phone.trim());
      // dev/local — SMS-ის გარეშე ტესტირება რომ შეიძლებოდეს
      if (response.debug_code) setCode(response.debug_code);
      setStep('code');
      startCooldown();
      Haptics.selectionAsync().catch(() => undefined);
    } catch (e) {
      fail(message(e));
    } finally {
      setBusy(false);
    }
  };

  const confirm = async () => {
    setBusy(true);
    setError(null);

    try {
      if (isUpgrade) {
        await upgradeGuest(phone.trim(), code.trim());
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
        Alert.alert(t('auth.title'), t('auth.upgraded'));
        router.back();
        return;
      }

      await signInWithOtp(phone.trim(), code.trim());
      await completeOnboarding();
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
      router.replace('/(tabs)');
    } catch (e) {
      fail(message(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      ambient={colors.rank}
      footer={
        step === 'phone' ? (
          <Button
            title={t('auth.sendCode')}
            loading={busy}
            disabled={phone.trim().length < 9}
            onPress={requestCode}
          />
        ) : (
          <View style={{ gap: space.sm }}>
            <Button title={t('common.continue')} loading={busy} disabled={code.trim().length < 4} onPress={confirm} />
            <Button
              title={cooldown > 0 ? t('auth.resendIn', { sec: cooldown }) : t('auth.resend')}
              variant="ghost"
              size="sm"
              disabled={cooldown > 0 || busy}
              onPress={requestCode}
            />
          </View>
        )
      }
    >
      <Reveal from="top" distance={8} style={{ alignSelf: 'flex-start', marginBottom: space.lg }}>
        <Tap onPress={() => router.back()}>
          <Text variant="label" tone="muted">
            ← {t('common.back')}
          </Text>
        </Tap>
      </Reveal>

      <Reveal index={1} style={{ gap: space.xs, marginBottom: space.xl }}>
        <Text variant="title">{isUpgrade ? t('auth.title') : t('auth.signInTitle')}</Text>
        <Text variant="bodySm" tone="muted">
          {step === 'code' ? t('auth.codeSub', { phone }) : isUpgrade ? t('auth.sub') : t('auth.signInSub')}
        </Text>
      </Reveal>

      {/* ორი ნაბიჯი ერთ ბარათში — ახალი ველი მარჯვნიდან შემოდის, როგორც ოსტატში */}
      <Animated.View style={shaking}>
        <Card>
          <Reveal key={step} from="right" distance={24}>
            <Text variant="overline" tone="muted" style={{ marginBottom: space.sm }}>
              {step === 'phone' ? t('auth.phone') : t('auth.codeTitle')}
            </Text>

            {step === 'phone' ? (
              <Field value={phone} onChange={setPhone} keyboardType="phone-pad" autoFocus maxLength={16} />
            ) : (
              <Field value={code} onChange={setCode} keyboardType="number-pad" autoFocus maxLength={6} numeric />
            )}
          </Reveal>

          <Fade visible={!!error}>
            <Text variant="caption" style={{ color: colors.danger, marginTop: space.sm }}>
              {error}
            </Text>
          </Fade>
        </Card>
      </Animated.View>

      {isUpgrade ? (
        <Reveal index={2}>
          <Text variant="caption" tone="muted" style={{ marginTop: space.md }}>
            {t('auth.guestNote')}
          </Text>
        </Reveal>
      ) : null}
    </Screen>
  );
}

function Field({
  value,
  onChange,
  numeric,
  ...rest
}: {
  value: string;
  onChange: (value: string) => void;
  numeric?: boolean;
} & Omit<React.ComponentProps<typeof TextInput>, 'value' | 'onChange' | 'onChangeText' | 'style'>) {
  return (
    <TextInput
      value={value}
      onChangeText={onChange}
      placeholderTextColor={colors.textDisabled}
      style={{
        height: 52,
        borderRadius: radius.md,
        paddingHorizontal: space.base,
        backgroundColor: colors.surfaceHi,
        borderWidth: border.hair,
        borderColor: colors.borderStrong,
        color: colors.text,
        fontFamily: numeric ? 'Unbounded_700Bold' : 'NotoSansGeorgian_500Medium',
        fontSize: numeric ? 22 : 17,
        letterSpacing: numeric ? 6 : 0,
        textAlign: numeric ? 'center' : 'left',
      }}
      {...rest}
    />
  );
}
