import { Tabs } from 'expo-router';
import { Platform, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, font, space } from '@/theme';
import { TabIcon } from '@/components/TabIcon';

export default function TabsLayout() {
  const { t } = useTranslation();
  const insets = useSafeAreaInsets();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.accent,
        tabBarInactiveTintColor: colors.textMuted,
        tabBarStyle: {
          backgroundColor: colors.bgElevated,
          borderTopColor: colors.border,
          borderTopWidth: Platform.select({ ios: 0.5, default: 1 }),
          // ხატულას თავზე აქტიური ტაბის მარკერია — სიმაღლე მას ითვალისწინებს
          height: 64 + insets.bottom,
          paddingTop: space.xs,
          paddingBottom: insets.bottom || space.sm,
        },
        tabBarLabelStyle: {
          fontFamily: font.textMedium,
          fontSize: 10.5,
          letterSpacing: 0.1,
        },
        // აქტიური ტაბის ზემოთ თხელი ლაიმის ხაზი — ერთადერთი
        // „ჩართული" ინდიკატორი, რომელიც ხატულასთან არ კონკურირებს
        tabBarBackground: () => (
          <View style={{ flex: 1, backgroundColor: colors.bgElevated }} />
        ),
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: t('tabs.home'),
          tabBarIcon: ({ color, focused }) => <TabIcon name="today" color={color} focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="library"
        options={{
          title: t('tabs.library'),
          tabBarIcon: ({ color, focused }) => <TabIcon name="library" color={color} focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="map"
        options={{
          title: t('tabs.map'),
          tabBarIcon: ({ color, focused }) => <TabIcon name="map" color={color} focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="league"
        options={{
          title: t('tabs.league'),
          tabBarIcon: ({ color, focused }) => <TabIcon name="league" color={color} focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: t('tabs.profile'),
          tabBarIcon: ({ color, focused }) => <TabIcon name="profile" color={color} focused={focused} />,
        }}
      />
    </Tabs>
  );
}
