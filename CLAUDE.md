# Kalisteni — სამუშაო წესები

მონორეპო: `backend/` (Laravel API + Filament) და `mobile/` (Expo / React Native).
სპეციფიკაცია: `calisthenics-app-tech-spec.md` — ის არის ჭეშმარიტების წყარო
სკოუპისთვის, XP-ის ფორმულებისთვის და ლიმიტებისთვის.

## გარემო

PHP და Postgres keg-only-ია — ბრძანებებამდე PATH:

```bash
export PATH="/opt/homebrew/opt/php@8.3/bin:/opt/homebrew/opt/postgresql@17/bin:$PATH"
```

სერვისები: `brew services start postgresql@17 redis`.
ბაზები: `kalisteni` (dev) და `kalisteni_test` (ტესტები), ორივეზე PostGIS ჩართული.

## ბრძანებები

```bash
# backend
cd backend
php artisan serve                 # http://localhost:8000
php artisan test                  # 62 ტესტი
php artisan exercises:fetch-media # სავარჯიშოს ლუპები ღია წყაროებიდან
./vendor/bin/pint                 # ფორმატირება (commit-მდე გაუშვი)
php artisan migrate:fresh --seed

# mobile
cd mobile
npx expo start
npx tsc --noEmit                  # ტიპების შემოწმება
npx expo run:ios                  # ნატიური build სიმულატორზე
```

## წესები, რომლებსაც არ ვარღვევთ

**XP მხოლოდ სერვერზე ითვლება.** კლიენტიდან მოსული `xp` არასდროს არ იკითხება.
თუ პლეიერში XP გჭირდება — ეს `src/lib/xp.ts`-ის *ესტიმაციაა* და UI-ში
ასეთად უნდა იყოს მონიშნული.

**`xp_ledger` append-only-ია.** მოდელი `UPDATE`/`DELETE`-ზე `LogicException`-ს
აგდებს. შესწორება მხოლოდ ახალი `reason: 'adjustment'` ჩანაწერით ხდება.
ყოველი რიგი ინახავს `k_snapshot`-ს — კოეფიციენტის შეცვლა ისტორიას არ ეხება.

**ჯამური XP არსად არ ინახება ველად.** ყოველთვის აგრეგირდება ledger-იდან
(`StatsService`) და Redis-ში ქეშირდება.

**ჯანმრთელობის ლიმიტები პროდუქტის მოთხოვნაა.** დღიური ჭერი 1800 XP,
სესია მაქს. 150 წთ, diminishing returns 100 გამეორებაზე, „ტკივილი მაქვს"
ღილაკი — ესენი არ არის ოპტიმიზაცია და მათ მოხსნა არ შეიძლება.

**ლიგები ჩაკეტილია 200 კვირეულ აქტიურამდე.** ცარიელი ლიგა უფრო მეტ ზიანს
აყენებს, ვიდრე მისი არარსებობა.

**ლუპები ღია წყაროებიდან ხელით არის დამაგრებული.**
`database/data/exercise_media_sources.json` არის ერთადერთი რუკა slug → კადრები.
ავტომატური ძებნა (Commons search, სახელით მიმსგავსება) არასწორ მოძრაობას
აბრუნებს — ეს ინსტრუქციული კონტენტია და არა დეკორაცია, ამიტომ ახალი
სავარჯიშოს წყარო ხელით ემატება ან `pending_own_footage`-ში ჩერდება.
GIF-ს `AnimatedGif` აწყობს წმინდა PHP-ით — ImageMagick/ffmpeg დამოკიდებულებად
არ ემატება. მედია `KALISTENI_MEDIA_DISK`-ზე (default `public`) წერია, არა
`FILESYSTEM_DISK`-ზე — R2 კრედენშელების გარეშე ჩაწერა ჩავარდებოდა.

**ატრიბუცია ავტომატურია.** `exercise_media.license` / `attribution_text` /
`source_url` ივსება ჩამოტვირთვისას; `/v1/attributions` და პროფილის ბლოკი
მისგან იგება. `own` და `public-domain` კრედიტს არ საჭიროებს, დანარჩენი — კი.

**სესია 2 სავარჯიშოზე და 3 წუთზე ნაკლები არ ჩაითვლება**
(`SessionSyncService::isTooThin`). ამიტომ თავისუფალი ვარჯიში კალათაა
(`store/freestyle.ts`) და არა „ერთი სავარჯიშო → დაწყება" — ლიმიტი UI-შივე ჩანს.

## კონვენციები

- **ტექსტი მობაილზე:** ნედლი `<Text>` არ გამოიყენება — მხოლოდ
  `@/components`-ის `Text`, თორემ ქართული ფონტი არ ჩაირთვება.
- **ლოკალიზაცია:** სამივე ფაილს (`ka`, `ru`, `en`) ერთი და იგივე გასაღებები
  უნდა ჰქონდეს. ახალი სტრიქონი სამივეში ერთდროულად ემატება.
- **გეო:** მანძილი ყოველთვის `geography`-ზე ითვლება (მეტრები). `geometry`-ზე
  გადასვლა მხოლოდ `ST_X` / `ST_Y`-სთვის.
- **სესიები immutable-ია.** კონფლიქტის მოგვარების ლოგიკა არ გვჭირდება;
  იდემპოტენტურობა `client_uuid`-ზეა.
- **მიგრაციები:** ახალი ცხრილი დაამატე ცალკე ფაილად, არსებულს ნუ შეცვლი,
  თუ ბაზა უკვე გაშვებულია სადმე გარდა ლოკალურისა.
- **ინვენტარის ორი ლექსიკონი:** პროფილში წვდომის დონეა (`none|bar|yard|gym`),
  სავარჯიშოზე ფიზიკური ტეგი (`pull_up_bar`…). თარგმანი მხოლოდ
  `ExerciseController::expandEquipment`-შია — სხვაგან ნუ გაამრავლებ.
- **მედიის რენდერერი:** GIF/სურათი → `expo-image`, mp4 → `expo-video`.
  არჩევანი `src/lib/media.ts`-შია; `expo-video`-ში ჩაწოდებული GIF შეცდომას
  არ აგდებს — უბრალოდ უძრავი რჩება, ამიტომ პირდაპირ ნუ გამოიყენებ.
- **მოძრაობა:** ახალ ანიმაციას ნუ დაწერ ხელით — `src/components/Motion.tsx`-ის
  `Reveal` / `CountUp` / `Pop` / `Pulse` / `Skeleton` / `SlideUp`-ს გამოიყენე,
  მრუდები კი `theme`-ის `easing`/`spring*`-იდან. ერთი ეკრანი = ერთი კასკადი
  (`index` თანმიმდევრობით), ხტუნვა მხოლოდ მიღწევაზე.
- **FlatList-ში კასკადი მხოლოდ პირველ ეკრანზეა** (`index < N ? index : 0`) —
  სხვაგვარად გადახვევისას ყოველი ახალი რიგი შეყოვნებით ჩნდება და სია
  „ჩამორჩება“ თითს.
- **Intervention Image v4 ამ პროექტში** `createImage()` / `decodeBinary()` /
  `encode(new JpegEncoder)`-ია, `valign()` არ არსებობს. GD-ს TTF გარეშე
  მხოლოდ bitmap ფონტი აქვს — ბარათის ტიპოგრაფია `resources/fonts`-იდან იკითხება.

## რაც ჯერ არ არის

11 მოძრაობის ილუსტრაცია (planche, tuck-planche, tuck-front-lever,
dragon flag, dead hang, hollow body, wall/knee push-up… — ღია წყაროში
არ არსებობს, სია `pending_own_footage`-შია), SMS პროვაიდერი,
Google/Apple Sign-In SDK კლიენტზე, R2-ის რეალური კრედენშელები,
თბილისის 200 მოედნის ხელით შევსება.
