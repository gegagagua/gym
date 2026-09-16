import { View, type StyleProp, type ViewStyle } from 'react-native';
import { Image } from 'expo-image';
import { VideoView, useVideoPlayer } from 'expo-video';
import { Text } from './Text';
import { colors, radius, space, border } from '@/theme';
import type { ResolvedLoop } from '@/lib/media';

export interface MediaLoopProps {
  source: ResolvedLoop | null;
  tint: string;
  aspectRatio?: number;
  /** ცარიელ მდგომარეობაში დიდი ასოები — სავარჯიშოს სახელიდან */
  initials?: string;
  emptyLabel?: string;
  style?: StyleProp<ViewStyle>;
}

/** ლუპის რენდერერი: GIF/სურათი → expo-image, mp4 → expo-video */
export function MediaLoop({
  source,
  tint,
  aspectRatio = 16 / 10,
  initials,
  emptyLabel,
  style,
}: MediaLoopProps) {
  // ჰუკი ყოველთვის უნდა გამოიძახოს — GIF-ზეც, როცა წყარო null-ია
  const player = useVideoPlayer(source?.kind === 'video' ? source.url : null, (instance) => {
    instance.loop = true;
    instance.muted = true;
    instance.play();
  });

  return (
    <View
      style={[
        {
          aspectRatio,
          borderRadius: radius.lg,
          overflow: 'hidden',
          backgroundColor: colors.surface,
          borderWidth: border.hair,
          borderColor: source ? `${tint}33` : colors.border,
        },
        style,
      ]}
    >
      {source?.kind === 'video' ? (
        <VideoView player={player} style={{ flex: 1 }} nativeControls={false} contentFit="cover" />
      ) : source ? (
        <Image
          source={{ uri: source.url }}
          style={{ flex: 1 }}
          contentFit="cover"
          transition={180}
          cachePolicy="disk"
        />
      ) : (
        <View
          style={{
            flex: 1,
            alignItems: 'center',
            justifyContent: 'center',
            gap: space.xs,
            padding: space.base,
          }}
        >
          {initials ? (
            <Text variant="display" style={{ color: `${tint}55`, fontSize: 30 }}>
              {initials.slice(0, 2).toUpperCase()}
            </Text>
          ) : null}

          {emptyLabel ? (
            <Text variant="caption" tone="muted" center>
              {emptyLabel}
            </Text>
          ) : null}
        </View>
      )}
    </View>
  );
}
