# Kalisteni

ტანვარჯიშის (calisthenics / street workout) მობილური აპლიკაცია ქართული
ბაზრისთვის — სავარჯიშო ფენა, გეიმიფიკაცია და ქუჩის მოედნების რუკა.

იმპლემენტაცია მიჰყვება `calisthenics-app-tech-spec.md` v1.0-ს.

```
gym/
├── backend/    Laravel 13 API + Filament ადმინი (PostgreSQL + PostGIS + Redis)
├── mobile/     Expo / React Native აპლიკაცია (SDK 57, expo-router)
└── calisthenics-app-tech-spec.md
```

---

## სწრაფი გაშვება

წინაპირობები დაინსტალირებულია Homebrew-ით: `php@8.3`, `composer`,
`postgresql@17`, `postgis`, `redis`, `node`.

```bash
# სერვისები
brew services start postgresql@17
brew services start redis

# --- backend ---
cd backend
composer install
cp .env.example .env && php artisan key:generate
createdb kalisteni && psql -d kalisteni -c "CREATE EXTENSION IF NOT EXISTS postgis;"
php artisan migrate --seed
php artisan serve                 # http://localhost:8000
php artisan horizon               # ცალკე ტერმინალში — queue workers

# --- mobile ---
cd ../mobile
npm install
npx expo run:ios                  # ან: npx expo run:android
```

ადმინ-პანელი: `http://localhost:8000/admin` — `admin@kalisteni.ge` / `password`
(მხოლოდ `APP_ENV=local`-ზე იქმნება).

---

## რა არის აწყობილი

### Backend (`backend/`)

| ფენა | სად |
|---|---|
| მონაცემთა მოდელი | `database/migrations/` — 30+ ცხრილი, PostGIS `GEOGRAPHY(POINT,4326)` |
| XP ძრავა | `app/Services/Xp/XpCalculator.php` |
| ანტი-ჩიტი | `app/Services/AntiCheatService.php` — 6 ევრისტიკა |
| ოფლაინ სინქი | `app/Services/SessionSyncService.php` — იდემპოტენტური `client_uuid`-ით |
| გეო | `app/Services/SpotService.php` — `ST_DWithin` / `ST_Distance` |
| ლიგები | `app/Services/LeagueService.php` — Redis sorted sets |
| ადმინი | `app/Filament/Resources/` — კონტენტი, მოდერაცია, კალიბრაცია |
| სავარჯიშოს ლუპები | `app/Services/Media/` + `php artisan exercises:fetch-media` |
| გაზიარების ბარათი | `app/Jobs/RenderShareCard.php` — 1080×1920, ბრენდის ფონტებით |
| ტესტები | `tests/` — 64 ტესტი, ნამდვილ Postgres+PostGIS ბაზაზე |

### Mobile (`mobile/`)

| ფენა | სად |
|---|---|
| დიზაინ-სისტემა | `src/theme/` + `src/components/` |
| ოფლაინ ბაზა | `src/db/` — expo-sqlite + Drizzle, სინქის რიგი |
| ვარჯიშის პლეიერი | `app/player.tsx` + `src/features/player/` |
| ონბორდინგი | `app/(onboarding)/` — 5 ნაბიჯი დონის ტესტით |
| ტაბები | `app/(tabs)/` — დღეს, ბიბლიოთეკა, მოედნები, ლიგა, პროფილი |
| მოძრაობა | `src/components/Motion.tsx` — Reveal, CountUp, Pop, Pulse, Skeleton |
| ავტორიზაცია | `app/auth.tsx` — ტელეფონით შესვლა და სტუმრის მიბმა |
| პროგრამები | `app/programs.tsx` — კატალოგი და გადართვა |
| ახალი მოედანი | `app/spot/new.tsx` — UGC, GPS-ის მიხედვით |
| ლოკალიზაცია | `src/i18n/` — ka / ru / en, 238 გასაღები თითოეულში |

---

## სავარჯიშოს ლუპები

ილუსტრაციები ღია წყაროებიდან იწყობა — რუკა ხელით არის შედგენილი
`backend/database/data/exercise_media_sources.json`-ში, რადგან ავტომატური
ძებნა არასწორ მოძრაობას აბრუნებს, ეს კი ინსტრუქციული კონტენტია.

```bash
cd backend
php artisan storage:link
php artisan exercises:fetch-media          # ან --only=push-up,burpee --force
```

| წყარო | ლიცენზია | რა მოაქვს |
|---|---|---|
| [free-exercise-db](https://github.com/yuhonas/free-exercise-db) | Unlicense (public domain) | 26 მოძრაობა, 2 კადრი თითო |
| [Wikimedia Commons](https://commons.wikimedia.org) | CC BY-SA | 6 ხელით შერჩეული ფაილი (burpee — 6 კადრი) |

კადრები 480×360-ზე იჭრება და **წმინდა PHP-ის ენკოდერით** (`AnimatedGif`)
ერთ მარყუჟიან GIF89a-დ იკვრება — ImageMagick და ffmpeg დამოკიდებულებად
არ გვჭირდება. შედეგი: 27 ანიმირებული ლუპი + 5 სტატიკური კადრი, სულ 5.6 MB.

დანარჩენი 11 (planche, front lever-ის tuck ვარიაცია, dragon flag, dead hang…)
ღია წყაროში არ არსებობს და საკუთარ გადაღებას ელოდება — სია ბრძანების
გამოსავალშია და `pending_own_footage`-ში.

ლიცენზია და ავტორი `exercise_media`-ში იწერება; `/v1/attributions` და
პროფილის ატრიბუციის ბლოკი ავტომატურად მისგან იგება — CC BY-SA-ზე კრედიტი
სავარჯიშოს ეკრანზეც ჩანს.

---

## დიზაინის მიმართულება — „NEON CONCRETE"

აპი dark-first-ია: ვარჯიში გარეთ ხდება და თეთრი ეკრანი მზეზე
არაფრისთვის გამოდგება.

| როლი | ფერი | სად |
|---|---|---|
| ფონი | `#06070A` ბეტონის შავი | ყველგან |
| ენერგია | `#D7FF3E` მჟავე ლაიმი | XP, მთავარი ქმედებები |
| რანგი | `#8B5CFF` ელექტრო-იისფერი | ლიგა, skill unlock |
| streak | `#FF6B35` ქარვისფერი | ჯაჭვი, დატვირთვა |
| ვერიფიკაცია | `#3EE8FF` ციანი | check-in, სტატიკა |

- **ტიპოგრაფია:** `Unbounded` ციფრებისთვის (XP, ტაიმერი), `Noto Sans Georgian`
  ტექსტისთვის. ქართული სიტყვები 25–40%-ით გრძელია — ღილაკებზე ტექსტი
  ერთ ხაზში იჭრება `adjustsFontSizeToFit`-ით.
- **სიღრმე:** ჩრდილის ნაცვლად hairline ბორდერი + ფერადი radial glow.
  შავ ფონზე ჩრდილი უხილავია.
- **ტექსტურა:** დეტერმინისტული წერტილოვანი „მარცვალი" ყველა ეკრანზე.
- **უკუკავშირი:** ყოველ შეხებას აქვს haptic — ვარჯიშის დროს ეკრანს არ იყურები.

დეტალები: `mobile/src/theme/colors.ts` და `mobile/src/theme/tokens.ts`.

---

## მოძრაობა

მოძრაობა აპში დეკორაცია არ არის — ის ან სტატუსს ამბობს, ან რიტმს იჭერს,
ან ყურადღებას მიმართავს. ერთი ენა: შემოსვლა რბილია (`easing.out`),
გასვლა მკვეთრი (`easing.in`), „ხტუნვა“ მხოლოდ მიღწევაზე (`springBouncy`).

| პრიმიტივი | სად |
|---|---|
| `Reveal` | ბლოკის შემოსვლა კასკადში (`index`-ით) — ყველა ეკრანზე |
| `CountUp` | XP, დონე, რანგი — ციფრი 0-დან ითვლება |
| `Pop` | მნიშვნელობა შეიცვალა: სეტი, გამეორება, კალათა |
| `Pulse` / `Breathe` | ცოცხალი სტატუსი: check-in, მოედანზე „ახლა ვარჯიშობს“, ატმოსფერო |
| `Skeleton` / `SkeletonRows` | ჩატვირთვა — ცარიელი ეკრანი აღარსად არის |
| `SlideUp` / `Fade` | კალათა, შეცდომები, გაზიარების ბარათი |

ვარჯიშის ეკრანზე მოძრაობა ინფორმაციაა:

- **3-2-1** — სესია იწყება ათვლით (`ReadyScreen`), თორემ პირველი
  გამეორებები ტელეფონის ჯიბეში ჩადებისას იკარგება;
- **დასვენება სუნთქავს** — რგოლი 8 წმ ციკლით ფართოვდება; ბოლო 3 წამზე
  რიტმი ორჯერ ჩქარდება და ფერი ლაიმისფერზე გადადის;
- **სეტის ჩაწერა** — ეკრანის თავზე ერთი ალი და `+N XP` ამოცურდება;
- **hold-ტაიმერი** — რგოლი სუნთქვის რიტმს აძლევს (სტატიკაზე სუნთქვის
  შეკავება ყველაზე ხშირი შეცდომაა), სამიზნის გადალახვა ინთება;
- **ღილაკის შენარჩუნება** — `+`/`−` ჩქარდება: 60 აჭიმის შეყვანა
  60 შეხებით არავის უნდა.

დეტალები: `mobile/src/components/Motion.tsx` და `mobile/src/theme/tokens.ts`
(`easing`, `stagger`, `spring*`).

---

## რა არ არის ჩადებული (განზრახ)

| ელემენტი | მიზეზი |
|---|---|
| Docker | არ იყო მოთხოვნილი — სერვისები ლოკალურად, Homebrew-ით |
| ვიდეო-ლუპები 11 მოძრაობაზე | ღია წყაროში არ არსებობს — hero მოძრაობები საკუთარი გადაღებაა (სპეც. 13.3) |
| SMS პროვაიდერი | `OtpService`-ს აქვს ინტერფეისი; local/testing-ზე კოდი პასუხში ბრუნდება |
| Google / Apple Sign-In SDK | სერვერის მხარე მზადაა (`/auth/social`); კლიენტზე SDK ჯერ არ არის ჩართული |
| Cloudflare R2 | `.env`-ში კონფიგურირებადია; ლოკალურად ნაგულისხმევი დისკი მუშაობს |
| 200 მოედანი თბილისში | `SpotSeeder` 20 საწყისს ჩადებს — დანარჩენი ხელით შევსებაა (სპეც. 9.3, რისკი R1) |

---

## ორი არქიტექტურული წესი, რომელსაც ყველაფერი ეყრდნობა

**1. XP მხოლოდ სერვერზე ითვლება.** კლიენტი ნედლ მონაცემებს აგზავნის
(`exercise_id`, `reps`, `duration_ms`, ტაიმსტემპები). კლიენტიდან მოსული
`xp` არსად არ იკითხება. აპში ნაჩვენები XP სესიის დროს არის *ესტიმაცია*
და ვიზუალურად ასეა აღნიშნული.

**2. `xp_ledger` append-only-ია.** არასდროს არ განახლდება და არ იშლება —
Eloquent მოდელი `UPDATE`/`DELETE`-ს გამონაკლისით აგდებს. ყოველი ჩანაწერი
ინახავს `k_snapshot`-ს, ანუ იმ მომენტში მოქმედ კოეფიციენტს. ეს იძლევა
კოეფიციენტების გადაკალიბრებას ისტორიის დაზიანების გარეშე — და
გადაკალიბრება პირველ 3 თვეში გარდაუვალია (სპეც. 10.3, რისკი R4).

---

## ტესტები

```bash
cd backend
createdb kalisteni_test && psql -d kalisteni_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
php artisan test          # 64 passed

cd ../mobile
npx tsc --noEmit
```

ტესტები ნამდვილ Postgres+PostGIS-ზე გადის და არა sqlite-ზე — გეო-ლოგიკას
sqlite ვერ დაფარავს.
