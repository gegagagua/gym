# Calisthenics App — ტექნიკური დოკუმენტი v1.0

**სტატუსი:** დრაფტი
**თარიღი:** 2026-08-19
**ბაზარი:** საქართველო (v1) → CIS / რუსულენოვანი ბაზრები (v2) → შოპი და კვება (v2–v3)
**ავტორი:** Gega

---

## 1. დოკუმენტის მიზანი

ეს დოკუმენტი აღწერს v1.0-ის სრულ ტექნიკურ და პროდუქტულ სპეციფიკაციას: სკოუპს, მონაცემთა მოდელს, API-ს, გეიმიფიკაციის ალგორითმებს, ინფრასტრუქტურას და გაშვების გეგმას. v2/v3 აღწერილია მხოლოდ იმ დონეზე, რაც v1-ის არქიტექტურულ გადაწყვეტილებებზე გავლენას ახდენს.

---

## 2. პროდუქტის მიმოხილვა

მობილური აპლიკაცია ტანვარჯიშისთვის (calisthenics / street workout), რომელიც აერთიანებს სამ ფენას:

1. **სავარჯიშო ფენა** — პროგრამები, სავარჯიშოების ბიბლიოთეკა, ვარჯიშის პლეიერი ტაიმერით, კალენდარი, ტრეკერი
2. **გეიმიფიკაციის ფენა** — XP ქულები სავარჯიშოს სირთულის მიხედვით, streak-ები, კვირეული ლიგები, გაზიარება
3. **გეო-სოციალური ფენა** — ქუჩის მოედნების რუკა, check-in-ები, მოედნის ლიდერბორდი, ტრენერების კონტაქტები

**მთავარი დიფერენციატორი:** ქართული და რუსული ლოკალიზაცია + ლოკალური მოედნების რუკა. არც ერთი გლობალური კონკურენტი (Calisteniapp, Thenx, Caliverse) არ ფარავს ქართულ ბაზარს.

### 2.1 პროდუქტის მიზნები (v1)

| მიზანი | მეტრიკა | სამიზნე (12 თვე) |
|---|---|---|
| მომხმარებელთა ბაზა | ინსტალაციები | 15 000–20 000 |
| აქტიურობა | MAU | 2 500–4 000 |
| შენარჩუნება | D30 retention | ≥ 12% |
| კონტენტის ვალიდაცია | დასრულებული სესია / MAU / კვირა | ≥ 2.5 |
| გეო-ფენა | ვერიფიცირებული მოედნები | ≥ 400 |
| შემოსავალი | — | **არ არის v1-ის მიზანი** |

v1 არის მომხმარებელთა ბაზისა და კონტენტის ვალიდაცია v2-ის (შოპი + ექსპორტი) მოსამზადებლად.

---

## 3. აუდიტორია და ბაზრის ვარაუდები

**პირველადი სეგმენტი:** ქართველი მამაკაცები 16–35 წელი. მოცულობა ~450–500 ათასი.

**მეორადი სეგმენტი:** რუსულენოვანი რეზიდენტები საქართველოში. რეალური მოცულობა **40–75 ათასი** (2024 წლის აღწერით 37 715 რუსეთის მოქალაქე მუდმივად ცხოვრობს; უფრო ფართო შეფასებით RU+UA+BY ემიგრანტები 108 222). საშუალო ასაკი 27–30. ტენდენცია კლებადია — ეს სეგმენტი **v2-ის ექსპორტის საცდელი პოლიგონია, არა v1-ის ძირითადი ბაზა**.

**მესამადი:** ქალები 18–30 — bodyweight/mobility ტრეკები. v1-ში ცალკე პროგრამებით, ცალკე მარკეტინგის გარეშე.

---

## 4. v1.0 სკოუპი

### 4.1 შედის

| # | მოდული | აღწერა |
|---|---|---|
| M1 | ავტორიზაცია | ტელეფონის OTP, Google Sign-In, Apple Sign-In, სტუმრის რეჟიმი |
| M2 | ონბორდინგი | ენა → მიზანი → ინვენტარი → დონის ტესტი |
| M3 | სავარჯიშოების ბიბლიოთეკა | 120–150 სავარჯიშო, ლუპ-ვიდეო, 3 ენა, ფილტრები |
| M4 | პროგრამები | 3 ტრეკი × 3 დონე, 8-კვირიანი ციკლები |
| M5 | ვარჯიშის პლეიერი | ტაიმერი, დასვენების ტაიმერი, აუდიო-სიგნალები, rep-ლოგი, ოფლაინ |
| M6 | კალენდარი და შემახსენებელი | ვარჯიშის განრიგი, ლოკალური და push ნოტიფიკაციები |
| M7 | ტრეკერი | streak, XP, კუნთების რუკა, PR-ების ისტორია, სხეულის მეტრიკები |
| M8 | გეიმიფიკაცია | XP, ბეიჯები, კვირეული ლიგა, მეგობრები |
| M9 | მოედნების რუკა | UGC მოედნები, ფოტო, ინვენტარი, check-in, ტრენერის კონტაქტი |
| M10 | გაზიარება | ავტო-გენერირებული სურათი Stories-ისთვის |
| M11 | ადმინ-პანელი | კონტენტის მართვა, მოდერაცია, კოეფიციენტების კალიბრაცია |

### 4.2 არ შედის v1-ში

მაღაზია • კვება • AI ფორმის ანალიზი • ჩატი / პირადი შეტყობინებები • wearables (Apple Watch / Wear OS) • ვიდეო-ქოუჩინგი • Apple Health / Health Connect ინტეგრაცია • ვებ-ვერსია • გადახდები

> **შენიშვნა:** Apple Health / Health Connect ინტეგრაცია v1.1-ში დაემატება. მონაცემთა მოდელი მისთვის მზად უნდა იყოს (`workout_sessions` ცხრილში `external_sync_id`, `calories_estimated`).

---

## 5. მომხმარებლის ძირითადი ნაკადები

### 5.1 ონბორდინგი

```
ენის არჩევა (ka / ru / en, default = device locale)
  → მიზანი (ძალა / კუნთი / წონის კლება / skills / ჯანმრთელობა)
  → სად ვარჯიშობ (სახლი უინვენტაროდ / სახლი + ტურნიკი / ეზოს მოედანი / დარბაზი)
  → ასაკი, სქესი, წონა, სიმაღლე (გამოტოვებადი)
  → დონის ტესტი
  → პროგრამის რეკომენდაცია
  → რეგისტრაცია (ან სტუმრად გაგრძელება)
```

**დონის ტესტი** — 3 სავარჯიშო, თითოეული max effort:

| ტესტი | დონე 1 | დონე 2 | დონე 3 | დონე 4 | დონე 5 |
|---|---|---|---|---|---|
| Push-up (max reps) | 0–5 | 6–15 | 16–30 | 31–50 | 50+ |
| Pull-up (max reps) | 0 | 1–3 | 4–8 | 9–15 | 15+ |
| Plank (max sec) | 0–20 | 21–45 | 46–90 | 91–150 | 150+ |

საბოლოო დონე = `round(avg(level_pushup, level_pullup, level_plank))`, თუმცა pull-up-ის დონე ჭრის ზედა ზღვარს: `final = min(avg_level, level_pullup + 1)`.

რეგისტრაცია ონბორდინგის **შემდეგ** ხდება — მომხმარებელმა ჯერ ღირებულება უნდა დაინახოს. სტუმრის მონაცემები ლოკალურად ინახება და რეგისტრაციისას მიგრირდება.

### 5.2 ვარჯიშის სესია

```
დღის ვარჯიშის არჩევა (პროგრამიდან ან თავისუფალი)
  → [არასავალდებულო] მოედანზე check-in (GPS)
  → გახურება (3–5 წთ, გამოტოვებადი)
  → სავარჯიშო ბლოკი:
       ვიდეო-ლუპი + ინსტრუქცია
       → სეტის შესრულება → reps/sec შეყვანა → დასვენების ტაიმერი
       → შემდეგი სეტი ...
  → სესიის შეჯამება: XP, ხანგრძლივობა, მოცულობა, ახალი PR-ები
  → [არასავალდებულო] გაზიარების ბარათი
```

პლეიერი **სრულად ოფლაინ** მუშაობს. სესია ლოკალურად იწერება და კავშირის აღდგენისას სინქრონდება.

### 5.3 მოედანზე check-in

```
რუკა → ახლომდებარე მოედნები (радиус 500 მ)
  → მოედნის ბარათი: ფოტოები, ინვენტარი, მდგომარეობა, ვინ ვარჯიშობს, ტრენერი
  → "აქ ვვარჯიშობ" → GPS ვალიდაცია (≤ 100 მ) → check-in აქტიურია 3 სთ
  → ვარჯიშის დასრულებისას +15% XP
```

---

## 6. XP და გეიმიფიკაციის მოდელი

### 6.1 საბაზისო ფორმულა

ყველა სავარჯიშოს აქვს კოეფიციენტი `k`, კალიბრირებული ისე რომ **1 კლასიკური push-up = 1.0 XP**.

```
reps-ზე დაფუძნებული:   base_xp = k × reps
hold-ზე დაფუძნებული:   base_xp = k × (seconds / 5)

final_xp = base_xp
         × weight_mod
         × tempo_mod
         × streak_mod
         × checkin_mod
         × diminishing_mod
```

### 6.2 მამრავლები

| მამრავლი | ფორმულა / მნიშვნელობა | დიაპაზონი |
|---|---|---|
| `weight_mod` | `1 + (added_kg / bodyweight_kg)` | 1.0 – 2.0 |
| `tempo_mod` | ნელი ტემპი (3-1-3) = 1.2, სხვა = 1.0 | 1.0 – 1.2 |
| `streak_mod` | 3+ დღე = 1.05 · 7+ = 1.10 · 14+ = 1.15 · 30+ = 1.25 | 1.0 – 1.25 |
| `checkin_mod` | ვერიფიცირებულ მოედანზე GPS check-in = 1.15 | 1.0 / 1.15 |
| `diminishing_mod` | იმავე დღეს იმავე სავარჯიშოს 100 გამეორების შემდეგ = 0.3 | 0.3 / 1.0 |

**ერთჯერადი ბონუსები (skill unlock):**

| Skill | ბონუსი |
|---|---|
| პირველი სრული pull-up | 200 XP |
| პირველი dip | 150 XP |
| პირველი muscle-up | 500 XP |
| პირველი handstand (10 წმ) | 400 XP |
| პირველი pistol squat | 250 XP |
| პირველი front lever (5 წმ) | 800 XP |
| პირველი planche (3 წმ) | 1500 XP |

### 6.3 კოეფიციენტების ცხრილი (ამონარიდი)

| სავარჯიშო | `k` | ერთეული |
|---|---|---|
| Wall push-up | 0.3 | rep |
| Incline push-up | 0.6 | rep |
| Knee push-up | 0.7 | rep |
| Squat | 0.8 | rep |
| **Push-up** | **1.0** | **rep** (საბაზისო) |
| Australian row | 1.2 | rep |
| Diamond push-up | 1.6 | rep |
| Archer push-up | 2.2 | rep |
| Dip (bar) | 2.0 | rep |
| Chin-up | 2.6 | rep |
| Pull-up | 3.0 | rep |
| Pistol squat | 3.0 | rep |
| Wide pull-up | 3.4 | rep |
| Pseudo planche push-up | 3.5 | rep |
| Archer pull-up | 5.0 | rep |
| Handstand push-up | 7.0 | rep |
| Muscle-up | 8.0 | rep |
| One-arm push-up | 9.0 | rep |
| Plank | 1.0 | 5 წმ |
| Hollow body hold | 1.5 | 5 წმ |
| L-sit | 2.5 | 5 წმ |
| Handstand hold | 3.0 | 5 წმ |
| Tuck front lever | 3.5 | 5 წმ |
| Back lever | 5.0 | 5 წმ |
| Front lever | 6.0 | 5 წმ |
| Tuck planche | 6.0 | 5 წმ |
| Planche | 10.0 | 5 წმ |

> სრული ცხრილი (~150 პოზიცია) ინახება `exercises.difficulty_coef` ველში და იმართება ადმინიდან. კოეფიციენტების გადაკალიბრება პირველ 3 თვეში **გარდაუვალია** — არქიტექტურა ამას უნდა უშვებდეს (იხ. 10.3).

### 6.4 ლიმიტები (ჯანმრთელობა + ანტი-ფარმინგი)

| ლიმიტი | მნიშვნელობა | მიზეზი |
|---|---|---|
| დღიური XP ჭერი | 1800 XP | გადავარჯიშების პრევენცია |
| ერთი სესიის მაქს. ხანგრძლივობა | 150 წთ | ტაიმერის დატოვების დეტექცია |
| Diminishing returns | 100 rep / სავარჯიშო / დღე | ფარმინგის პრევენცია |
| მინიმალური სესია | 3 წთ და 2 სავარჯიშო | ცარიელი სესიების ფილტრი |
| დასვენების დღე | **არ წყვეტს streak-ს**, თუ mobility/stretch დაილოგება | აღდგენის წახალისება |

> **კრიტიკული:** ლიდერბორდი + დღიური ქულები + 16–25 წლის მამაკაცების აუდიტორია = გადავარჯიშებისა და ტრავმის რეალური რისკი. ეს ლიმიტები **პროდუქტის მოთხოვნაა, არა ოპტიმიზაცია** — v1-ში უნდა ჩაიდოს.

---

## 7. ლიგები და ლიდერბორდი

### 7.1 კვირეული ლიგა

გლობალური all-time ბორდი **არ გამოიყენება** — 3 თვეში ის ერთი და იმავე მომხმარებლების საკუთრება ხდება და დანარჩენების მოტივაციას კლავს.

**მოდელი:** 30-კაციანი დივიზიონები, კვირეული ციკლი (ორშაბათი 00:00 → კვირა 23:59 Asia/Tbilisi).

| დივიზიონი | # |
|---|---|
| ბრინჯაო | 1 |
| ვერცხლი | 2 |
| ოქრო | 3 |
| პლატინა | 4 |
| ელიტა | 5 |

- ტოპ 7 → ადის დივიზიონით ზემოთ
- ბოლო 7 → ეშვება (ბრინჯაოდან არ ეშვება)
- დანარჩენები რჩებიან
- ჯგუფის ფორმირება: იმავე დივიზიონის მომხმარებლები, რომლებმაც ბოლო 7 დღეში ≥1 სესია დაასრულეს

**Cold-start დაცვა:** ლიგები **გააქტიურდება მხოლოდ მაშინ, როცა კვირაში ≥ 200 აქტიური მომხმარებელია.** მანამდე UI აჩვენებს პირად streak-სა და მეგობრების ბორდს. 12-კაციანი ლიგა უფრო მეტ ზიანს აყენებს, ვიდრე მის არარსებობას.

### 7.2 პარალელური ბორდები

| ბორდი | სკოუპი | პერიოდი |
|---|---|---|
| ლიგა | 30-კაციანი დივიზიონი | კვირა |
| მოედნის ბორდი | კონკრეტული spot | კვირა / თვე |
| ქალაქის ბორდი | city_id | თვე |
| მეგობრები | follows | ყოველთვის |
| PR ბორდი | ვერიფიცირებული რეკორდები სავარჯიშოს მიხედვით | all-time |

მოედნის ბორდი ყველაზე ძლიერი სოციალური კაუჭია — "ვინ არის ამ ეზოში №1" ლოკალურ კონკურენციას ქმნის და check-in-ებს ასტიმულირებს.

---

## 8. ვერიფიკაცია და ანტი-ჩიტი

| დონე | პირობა | რაში ითვლება |
|---|---|---|
| **T0** | ხელით შეყვანილი, აპი სესიის დროს არ იყო გახსნილი | პირადი სტატისტიკა; ლიგაში მაქს. 300 XP/დღე |
| **T1** | სესია აპში, ტაიმერით, რეალისტური დროის შტამპებით | სრული კვირეული XP |
| **T2** | T1 + GPS check-in ვერიფიცირებულ მოედანზე | +15%, მოედნის ბორდი |
| **T3** | ვიდეო-PR, მოდერირებული | all-time PR ბორდი, ბეიჯები |

### 8.1 ევრისტიკები

```
FLAG_IMPOSSIBLE_RATE   სეტი შესრულდა < 0.8 წმ/გამეორებაზე
FLAG_NO_REST           სეტებს შორის დასვენება < 5 წმ, ზედიზედ 3+ სეტი
FLAG_ROUND_NUMBERS     ყველა სეტი ზუსტად 20/50/100, ვარიაციის გარეშე
FLAG_GPS_JUMP          check-in-ები 2 მოედანზე < 10 წთ ინტერვალით და > 3 კმ დაშორებით
FLAG_CLOCK_SKEW        მოწყობილობის დრო სერვერისგან > 5 წთ განსხვავდება
FLAG_VELOCITY          დღიური XP > 95-ე პროცენტილზე 3 დღე ზედიზედ
```

2+ ფლაგი ერთ სესიაზე → სესია `flagged` სტატუსით ინახება, პირად სტატისტიკაში ითვლება, ლიგაში არა. 5+ დროშა კვირაში → shadow-demote ლიგიდან, მოდერატორის რევიუ.

### 8.2 სერვერული ვალიდაცია

XP **მხოლოდ სერვერზე ითვლება.** კლიენტი აგზავნის ნედლ მონაცემებს (`exercise_id`, `reps`, `duration_ms`, `started_at`, `completed_at`, `checkin_id`), სერვერი ითვლის და `xp_ledger`-ში წერს. კლიენტიდან მოსული `xp` მნიშვნელობა იგნორირდება.

---

## 9. მოედნების მოდული

### 9.1 მონაცემები

მოედნის ბარათი შეიცავს: სახელი, კოორდინატები, ფოტოები (მაქს. 6), ინვენტარის თეგები, მდგომარეობის შეფასება (1–5), ტიპი, წვდომა, განათება, ბოლო check-in-ები, დაკავშირებული ტრენერი.

**ინვენტარის თეგები:** `pull_up_bar`, `parallel_bars`, `low_bar`, `wall_bars`, `rings`, `monkey_bars`, `horizontal_ladder`, `bench`, `rope`, `ab_bench`, `outdoor_gym_machines`

**ტიპი:** `yard` (ეზო) · `park` · `school` · `stadium` · `commercial`

### 9.2 UGC და მოდერაცია

```
მომხმარებელი ამატებს მოედანს
  → სტატუსი: pending
  → ავტო-შემოწმება: დუბლიკატი 50 მ რადიუსში? ფოტო არსებობს?
  → მოდერატორის დადასტურება (ადმინ-პანელი)
  → სტატუსი: verified → ჩნდება რუკაზე, ჩართავს checkin_mod-ს
```

დამამატებელი იღებს 100 XP დადასტურებულ მოედანზე.

### 9.3 Cold-start (კრიტიკული)

**ცარიელი რუკა = მკვდარი აპი.** გაშვებამდე თბილისში ≥ 200 მოედანი უნდა იყოს შევსებული ხელით: ვაკე, საბურთალო, დიღომი, გლდანი, ისანი, ვარკეთილი, ნაძალადევი, ლისი, მთაწმინდის პარკი, სპორტული სკოლების ეზოები. ეს არის 3–4 საშაბათო და ის განსაზღვრავს გაშვება გამოვა თუ არა.

დამატებით: ბათუმი (40), ქუთაისი (30), რუსთავი (20).

### 9.4 ტრენერები

```
trainers: name, bio (3 ენა), photo, contact_instagram, contact_phone,
          contact_telegram, spot_ids[], is_verified, listing_tier
```

`listing_tier`: `free` (მხოლოდ სახელი) | `basic` | `featured`. **ეს არის v1-ის ერთადერთი რეალური მონეტიზაცია** — B2B ლისტინგი ქართულ ბაზარზე უფრო ადრე გაიყიდება, ვიდრე მომხმარებლის სუბსკრიფშენი.

---

## 10. მონაცემთა მოდელი

### 10.1 ძირითადი ცხრილები

```
users
  id, phone, email, provider, provider_id, username, avatar_url,
  locale, timezone, created_at, last_active_at, deleted_at

profiles
  user_id, birth_year, gender, height_cm, weight_kg,
  level (1-5), goal, equipment_json, city_id, is_public

exercises
  id, slug, category, force (push|pull|static|legs|core),
  mechanic (compound|isolation), unit (reps|seconds),
  difficulty_coef DECIMAL(5,2), level_min, level_max,
  equipment_json, primary_muscles_json, secondary_muscles_json,
  progression_from_id, progression_to_id, skill_group,
  is_skill_unlock, unlock_bonus_xp, is_active

exercise_translations
  exercise_id, locale, name, short_desc, instructions_json,
  common_mistakes_json, UNIQUE(exercise_id, locale)

exercise_media
  exercise_id, type (loop|video|thumbnail|anatomy),
  url, width, height, duration_ms, sort_order,
  license (own|cc-by-sa|public-domain|licensed),
  attribution_text, source_url

programs
  id, slug, track (home|bar|skills), level, duration_weeks,
  days_per_week, is_premium, is_active

program_translations
  program_id, locale, title, description

program_days
  program_id, week_no, day_no, title_key, type (workout|rest|test)

program_day_exercises
  program_day_id, exercise_id, sort_order, sets,
  target_reps, target_seconds, rest_seconds, tempo, notes_key

workout_sessions
  id, user_id, program_day_id (nullable), spot_checkin_id (nullable),
  started_at, completed_at, duration_ms, source (program|freestyle|test),
  verification_tier (0-3), status (completed|flagged|rejected),
  flags_json, total_xp, external_sync_id, client_uuid UNIQUE

session_sets
  id, session_id, exercise_id, set_no, reps, seconds,
  added_weight_kg, tempo, rest_after_ms, started_at, completed_at

personal_records
  user_id, exercise_id, metric (max_reps|max_seconds|max_weight),
  value, session_id, achieved_at, verification_tier,
  UNIQUE(user_id, exercise_id, metric)

xp_ledger                        -- APPEND ONLY
  id, user_id, session_id (nullable), exercise_id (nullable),
  reason (workout|skill_unlock|spot_added|referral|adjustment),
  raw_value, k_snapshot, multipliers_json, xp INT,
  occurred_at, created_at

streaks
  user_id, current_days, longest_days, last_activity_date,
  freeze_tokens, updated_at

leagues
  id, division (1-5), week_start_date, status (active|closed)

league_members
  league_id, user_id, xp_week, rank_final, movement (up|down|stay)

spots
  id, name, location GEOGRAPHY(POINT,4326), city_id,
  type, access (public|paid|restricted), condition_rating,
  has_lighting, description_json, status (pending|verified|rejected),
  created_by_user_id, verified_at, checkin_count

spot_equipment
  spot_id, equipment_tag, quantity, condition

spot_media
  spot_id, url, uploaded_by_user_id, is_primary, status

spot_checkins
  id, user_id, spot_id, checked_in_at, expires_at,
  accuracy_m, is_valid

trainers
  id, user_id (nullable), name, photo_url, bio_json,
  contact_instagram, contact_phone, contact_telegram,
  is_verified, listing_tier, expires_at

trainer_spots
  trainer_id, spot_id

follows
  follower_id, following_id, created_at

badges / user_badges
  code, icon, criteria_json / user_id, badge_id, earned_at

devices
  user_id, push_token, platform, app_version, locale, last_seen_at
```

### 10.2 ინდექსები

```sql
CREATE INDEX idx_spots_location ON spots USING GIST (location);
CREATE INDEX idx_spots_status_city ON spots (status, city_id);
CREATE INDEX idx_xp_ledger_user_time ON xp_ledger (user_id, occurred_at DESC);
CREATE INDEX idx_sessions_user_time ON workout_sessions (user_id, started_at DESC);
CREATE INDEX idx_sets_session ON session_sets (session_id);
CREATE INDEX idx_checkins_user_active ON spot_checkins (user_id, expires_at);
CREATE UNIQUE INDEX idx_sessions_client_uuid ON workout_sessions (client_uuid);
```

### 10.3 არქიტექტურული პრინციპი: append-only XP

`xp_ledger` არასდროს არ განახლდება და არ იშლება. მომხმარებლის ჯამური XP **არასდროს ინახება ცვალებად ველად** — ის აგრეგირდება ledger-იდან და ქეშირდება Redis-ში.

თითოეული ჩანაწერი ინახავს `k_snapshot`-ს — იმ მომენტში მოქმედ კოეფიციენტს. ეს იძლევა შესაძლებლობას:

- კოეფიციენტების გადაკალიბრება ისტორიის დაზიანების გარეშე
- ჩიტინგის რეტროსპექტული აუდიტი
- ბალანსის ცვლილებების A/B ტესტირება
- სპორული სესიების უკუგება `adjustment` ჩანაწერით (და არა წაშლით)

Redis: `ZADD league:{league_id} {xp_week} {user_id}` — ლიგის რანჟირება sorted set-ით, გადათვლა ledger-იდან ყოველ 5 წუთში (ან სესიის დასრულებისას ინკრემენტულად).

---

## 11. API

Base: `https://api.<domain>/v1` · Auth: Bearer JWT (Laravel Sanctum) · Content-Type: `application/json`
Locale: `Accept-Language: ka | ru | en`

### 11.1 ავტორიზაცია

```
POST   /auth/otp/request          { phone }
POST   /auth/otp/verify           { phone, code, device }        → { token, user }
POST   /auth/social               { provider, id_token, device } → { token, user }
POST   /auth/guest                { device_uuid }                → { token, user }
POST   /auth/refresh
DELETE /auth/logout
DELETE /account                   -- სრული წაშლა (App Store მოთხოვნა)
```

### 11.2 პროფილი და კონტენტი

```
GET    /me
PATCH  /me                        { locale, timezone, ... }
PATCH  /me/profile                { level, goal, equipment, weight_kg, ... }
GET    /me/stats                  ?period=week|month|all
GET    /me/records

GET    /exercises                 ?category&equipment&level&skill_group&updated_since
GET    /exercises/{id}
GET    /programs                  ?track&level
GET    /programs/{id}             -- სრული სტრუქტურა: weeks → days → exercises
POST   /programs/{id}/enroll
GET    /me/program                -- მიმდინარე პროგრამა და პროგრესი
```

### 11.3 სესიები (ოფლაინ სინქის ბირთვი)

```
POST   /sessions/sync
```

**Request:**
```json
{
  "sessions": [{
    "client_uuid": "9c3f...",
    "program_day_id": 412,
    "spot_checkin_id": 88123,
    "started_at": "2026-08-19T07:14:02Z",
    "completed_at": "2026-08-19T07:51:40Z",
    "source": "program",
    "device_clock_offset_ms": 240,
    "sets": [{
      "exercise_id": 17, "set_no": 1, "reps": 12,
      "seconds": null, "added_weight_kg": 0, "tempo": "normal",
      "rest_after_ms": 90000,
      "started_at": "2026-08-19T07:16:00Z",
      "completed_at": "2026-08-19T07:16:31Z"
    }]
  }]
}
```

**Response:**
```json
{
  "results": [{
    "client_uuid": "9c3f...",
    "session_id": 55129,
    "status": "completed",
    "verification_tier": 2,
    "xp_awarded": 412,
    "flags": [],
    "new_records": [{ "exercise_id": 17, "metric": "max_reps", "value": 12 }],
    "unlocked": [{ "code": "first_muscle_up", "bonus_xp": 500 }]
  }],
  "user_totals": { "xp_total": 18432, "streak_days": 11, "level": 3 }
}
```

`client_uuid` იდემპოტენტურობის გასაღებია — განმეორებითი გაგზავნა დუბლიკატს არ ქმნის.

### 11.4 გეიმიფიკაცია

```
GET    /league/current            → { division, week_start, members[], my_rank }
GET    /leaderboard/spot/{id}     ?period=week|month
GET    /leaderboard/city/{id}     ?period=month
GET    /leaderboard/friends
GET    /leaderboard/records/{exercise_id}
POST   /share/card                { type, period } → { image_url, expires_at }
```

### 11.5 მოედნები

```
GET    /spots/nearby              ?lat&lng&radius=1000&equipment[]
GET    /spots/{id}
POST   /spots                     -- UGC დამატება (multipart, ფოტოებით)
POST   /spots/{id}/media
POST   /spots/{id}/checkin        { lat, lng, accuracy_m } → { checkin_id, expires_at }
POST   /spots/{id}/rating         { condition_rating }
GET    /trainers                  ?spot_id&city_id
```

### 11.6 კონტენტის სინქი

```
GET /content/manifest?since=2026-08-01T00:00:00Z&locale=ka
```

აბრუნებს შეცვლილი სავარჯიშოების, პროგრამებისა და მედიის სიას ჰეშებით. კლიენტი მხოლოდ დელტას ტვირთავს. ეს კრიტიკულია — 150 სავარჯიშოს მედია ~120 MB-ია და ყოველ გაშვებაზე მისი გადმოწერა მიუღებელია.

---

## 12. არქიტექტურა

### 12.1 სტეკი

| ფენა | ტექნოლოგია | დასაბუთება |
|---|---|---|
| მობაილი | React Native + Expo (SDK 52+) | არსებული გამოცდილება, ერთი კოდბეისი |
| სტეიტი | Zustand + TanStack Query | მსუბუქი, ოფლაინ-friendly |
| ლოკალური DB | expo-sqlite + Drizzle ORM | სესიების ოფლაინ ჩაწერა |
| მედია | expo-video (H.265 ლუპები) | GIF-ზე 5–10× მსუბუქი |
| რუკა | react-native-maps + Google Maps SDK | PostGIS-თან თავსებადი |
| Backend | Laravel 11 (PHP 8.3) | არსებული სტეკი, კონტენტ-მძიმე პროექტს უხდება |
| ადმინი | Filament 3 | კონტენტის მართვა და მოდერაცია out-of-the-box |
| Auth | Laravel Sanctum | |
| DB | PostgreSQL 16 + PostGIS 3.4 | გეო-მოთხოვნები |
| Cache / Queue | Redis 7 | ლიგების sorted sets, queue |
| ფაილები | Cloudflare R2 + CDN | S3-თავსებადი, egress უფასო |
| Push | Expo Notifications → FCM / APNs | |
| ანალიტიკა | PostHog (self-hosted) | GDPR-friendly, ღირებულება |
| შეცდომები | Sentry | |
| CI/CD | GitHub Actions + EAS Build | |
| ჰოსტინგი | Hetzner CPX31 (v1) | ~€15/თვე, საკმარისი 5k MAU-ზე |

### 12.2 ინფრასტრუქტურის დიაგრამა

```
┌─────────────────────────────────────────────┐
│  React Native (iOS / Android)               │
│  ├── SQLite (ოფლაინ სესიები + კონტენტის ქეში)│
│  ├── Sync Queue (background task)           │
│  └── Media Cache (ლუპები, ფოტოები)          │
└──────────────────┬──────────────────────────┘
                   │ HTTPS / JWT
        ┌──────────▼──────────┐      ┌──────────────┐
        │  Cloudflare (CDN +  │◄─────┤ R2 (მედია)   │
        │  WAF + rate limit)  │      └──────────────┘
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────────────────────┐
        │  Laravel API (Octane / FrankenPHP)  │
        │  ├── XP Calculation Service         │
        │  ├── Anti-cheat Heuristics          │
        │  ├── Spot Geo Service (PostGIS)     │
        │  └── Share Card Renderer            │
        └────┬──────────────────┬─────────────┘
             │                  │
     ┌───────▼──────┐    ┌──────▼──────┐
     │ PostgreSQL   │    │   Redis     │
     │ + PostGIS    │    │ ლიგები/queue│
     └──────────────┘    └─────────────┘
                   │
        ┌──────────▼──────────┐
        │  Horizon Workers    │
        │  ├── ლიგების როტაცია│
        │  ├── Push კამპანიები│
        │  ├── Share რენდერი  │
        │  └── მედია პროცესინგ│
        └─────────────────────┘
```

### 12.3 ოფლაინ სინქის ალგორითმი

```
1. სესია სრულდება ოფლაინში
   → იწერება SQLite-ში: status = 'pending', client_uuid = uuid_v4()
   → ლოკალურად ჩანს "ვარჯიში დასრულებულია", XP ნაჩვენებია როგორც
     სავარაუდო (client-side ესტიმაცია, ვიზუალურად აღნიშნული)

2. კავშირის აღდგენა (NetInfo listener) ან აპის გახსნა
   → background task აგზავნის pending სესიებს პარტიებად (max 20)

3. სერვერი:
   → იდემპოტენტურობის შემოწმება client_uuid-ით
   → XP-ის სერვერული გამოთვლა (კლიენტის მნიშვნელობა იგნორირდება)
   → ანტი-ჩიტის ევრისტიკები
   → xp_ledger ჩანაწერი
   → პასუხი ნამდვილი XP-ით

4. კლიენტი:
   → status = 'synced', XP განახლდება ნამდვილით
   → თუ განსხვავებაა > 15%, ჩუმად სწორდება (ბანერის გარეშე)

კონფლიქტები: სესიები immutable არიან — კონფლიქტი შეუძლებელია.
პროფილის ველები: last-write-wins updated_at-ის მიხედვით.
```

**საათის მანიპულაცია:** კლიენტი აგზავნის `device_clock_offset_ms`-ს. > 5 წუთი განსხვავება სერვერის დროსთან → `FLAG_CLOCK_SKEW`, verification_tier ეცემა T0-მდე.

---

## 13. კონტენტის პაიპლაინი

### 13.1 წყაროები და ლიცენზიები

| წყარო | ლიცენზია | გამოყენება | შენიშვნა |
|---|---|---|---|
| free-exercise-db | Unlicense (public domain) | **მხოლოდ JSON სტრუქტურა და მეტადატა** | სურათების წარმომავლობა რეპოში დაუზუსტებელია — ღია issue #13 |
| wger | CC-BY-SA 3.0 (მონაცემები) | ბაზისური სავარჯიშოების ვიზუალი | საჭიროა ატრიბუციის ეკრანი |
| Wikimedia Commons | CC-BY / CC-BY-SA / PD | ანატომიური სქემები | თითოეულს ცალკე შემოწმება |
| **საკუთარი გადაღება** | own | **60 hero calisthenics მოძრაობა** | მთავარი აქტივი |
| Mixamo + Blender | royalty-free | გრძელი კუდი, 3D ანიმაციები | |
| Envato Elements | კომერციული სუბსკრიფშენი | სარეზერვო ვარიანტი hero-სთვის | ~$16/თვე |

> **აკრძალული:** კონკურენტი აპებიდან მედიის ამოღება. DMCA takedown Google Play-ზე კლავს **დეველოპერის ანგარიშს**, არა მხოლოდ აპს — ეს იმავე ანგარიშზე არსებულ სხვა პროექტებსაც ეხება.

**მნიშვნელოვანი აღმოჩენა:** ღია ბაზები დარბაზზეა ორიენტირებული (dumbbell, cable, machine). Calisthenics-ის ბირთვი — muscle-up, front lever progressions, planche lean, L-sit, pistol squat — მათში პრაქტიკულად არ არის. ეს კონტენტი **აუცილებლად საკუთარი უნდა იყოს**.

`exercise_media` ცხრილში `license`, `attribution_text` და `source_url` ველები სავალდებულოა — ატრიბუციის ეკრანი მათგან გენერირდება ავტომატურად.

### 13.2 მედიის ფორმატები

| ტიპი | ფორმატი | პარამეტრები | ზომა |
|---|---|---|---|
| სავარჯიშოს ლუპი | MP4 (H.265, muted) | 720×720, 3–5 წმ, 24fps, CRF 28 | 80–200 KB |
| Fallback | WebP animated | 480×480 | 150–400 KB |
| Thumbnail | WebP | 320×320 | 15–30 KB |
| მოედნის ფოტო | WebP | 1280×960 + 400×300 | 80 KB / 15 KB |

**GIF არ გამოიყენება** — იმავე ხარისხზე 5–10× მძიმეა.

**პროცესინგი:** ატვირთვა → queue job → ffmpeg ტრანსკოდი 3 ვარიანტში → R2 → CDN. ლოკალური ქეშირება მოთხოვნისამებრ (პროგრამის ჩამოტვირთვისას მისი ყველა სავარჯიშოს მედია წინასწარ ჩამოიტვირთება).

### 13.3 საკუთარი გადაღების გეგმა

- **ლოკაცია:** ეზოს მოედანი (ბუნებრივი კონტექსტი) + თეთრი ფონი სტუდიაში
- **მოდელი:** 1 მამრობითი + 1 მდედრობითი calisthenics ატლეტი
- **მოცულობა:** 60 მოძრაობა × 2 რაკურსი (გვერდი + წინა)
- **აღჭურვილობა:** ტელეფონი 4K/60, სამფეხა, LED პანელი
- **დრო:** 2 დღე
- **ბიუჯეტი:** ~600–1000 ₾ (მოდელების ჰონორარი)

---

## 14. ლოკალიზაცია

| ენა | კოდი | სტატუსი v1 |
|---|---|---|
| ქართული | `ka` | სრული (default GE-ში) |
| რუსული | `ru` | სრული |
| ინგლისური | `en` | სრული (fallback) |

- ინტერფეისი: `i18next` + JSON ფაილები, Crowdin-ის გარეშე v1-ში
- კონტენტი: `*_translations` ცხრილები, ადმინიდან რედაქტირებადი
- ნაგულისხმევი ენა: მოწყობილობის locale → fallback `en`
- **ქართული ტიპოგრაფია:** BPG Nino Mtavruli / Noto Sans Georgian, ტესტირება გრძელ სიტყვებზე (ღილაკებზე ტექსტი ხშირად სცილდება)
- რიცხვები და თარიღები: `Intl` API
- Push ნოტიფიკაციები: `devices.locale`-ის მიხედვით

---

## 15. ნოტიფიკაციები

| ტიპი | დრო | არხი | პირობა |
|---|---|---|---|
| ვარჯიშის შეხსენება | მომხმარებლის არჩეული | Local | აქტიური პროგრამა |
| Streak-ის რისკი | 20:00 ლოკალური | Push | დღეს სესია არ არის, streak ≥ 3 |
| ლიგის შედეგი | კვირა 22:00 | Push | ლიგის წევრი |
| ლიგის ბოლო საათები | კვირა 18:00 | Push | promotion/demotion ზონაში |
| ახალი მოედანი ახლოს | — | Push | დადასტურდა მოედანი < 1 კმ |
| მეგობარმა გაუსწრო | მაქს. 1×/დღე | Push | მეგობრების ბორდი |
| Win-back | 7 / 14 / 30 დღე | Push | არააქტიური |

**ლიმიტი: მაქს. 2 push დღეში.** მესამე იბლოკება queue-ს დონეზე. აგრესიული ნოტიფიკაციები v1-ის ყველაზე გავრცელებული მიზეზია აპის წაშლისა.

---

## 16. ანალიტიკა და KPI

**Event-ები:** `onboarding_started/completed`, `level_test_completed`, `program_enrolled`, `session_started/completed/abandoned`, `set_logged`, `spot_checkin`, `spot_created`, `share_card_generated`, `league_promoted/demoted`, `pr_achieved`, `skill_unlocked`, `push_opened`, `paywall_viewed`

**ძირითადი KPI:**

| მეტრიკა | სამიზნე |
|---|---|
| Onboarding completion | ≥ 65% |
| D1 / D7 / D30 retention | 40% / 20% / 12% |
| სესია / MAU / კვირა | ≥ 2.5 |
| სესიის დასრულების % | ≥ 75% |
| Check-in-იანი სესიები | ≥ 20% |
| Share-ის კონვერსია | ≥ 8% დასრულებულ სესიაზე |

---

## 17. უსაფრთხოება და პერსონალური მონაცემები

- HTTPS only, HSTS, certificate pinning მობაილზე
- JWT: 15 წთ access + 30 დღე refresh, rotation-ით
- Rate limiting: `/auth/otp/request` — 3/სთ/ტელეფონზე; `/sessions/sync` — 60/სთ; `/spots` POST — 10/დღე
- UGC ფოტოები: EXIF-ის სრული გაწმენდა ატვირთვისას (GPS-ის გაჟონვის თავიდან აცილება)
- GPS: მხოლოდ check-in-ის მომენტში, ფონური თრექინგი **არ ხდება**
- ანგარიშის წაშლა: აპიდან, 30 დღიანი grace, შემდეგ hard delete (Apple-ის მოთხოვნა)
- სხეულის მეტრიკები (წონა, სიმაღლე): არასდროს ჩანს საჯარო პროფილში და ლიდერბორდზე
- საქართველოს პერსონალურ მონაცემთა დაცვის კანონი + GDPR (ევროპული მომხმარებლებისთვის v2-დან)
- 16 წლამდე მომხმარებლები: ონბორდინგში ასაკის შემოწმება; 16-ზე ქვემოთ ლიდერბორდი და სოციალური ფუნქციები გამორთულია

---

## 18. ჯანმრთელობის უსაფრთხოება

| მოთხოვნა | იმპლემენტაცია |
|---|---|
| დისკლეიმერი | ონბორდინგში ერთხელ, ბიბლიოთეკის ეკრანზე მუდმივად |
| დღიური XP ჭერი | 1800 XP |
| დასვენების დღეები | პროგრამებში ჩაშენებული, streak-ს არ წყვეტს |
| ტკივილის ღილაკი | სესიის განმავლობაში "ტკივილი მაქვს" → სესია ჩერდება, რჩევა |
| პროგრესიის ბლოკი | შემდეგ საფეხურზე გადასვლა მხოლოდ მიმდინარეზე კრიტერიუმის დაკმაყოფილებით |
| ფორმის აქცენტი | ყოველი სავარჯიშოს "ხშირი შეცდომები" ბლოკი სავალდებულოა |

აპი **არ იძლევა სამედიცინო ან სამკურნალო რჩევებს** და არ ითვლის კალორიულ დეფიციტს v1-ში.

---

## 19. v2 / v3 გაფართოება (არქიტექტურული მოთხოვნები v1-ისთვის)

### 19.1 Feature flags per country

v2-ში შოპი მუშაობს **მხოლოდ საქართველოში** (ლოგისტიკა), ხოლო ექსპორტის ვერსია შოპის გარეშე იქნება. თუ ეს v1-ში არ გაითვალისწინება, v2-ზე კოდბეისი ორად გაიხლიჩება.

```
feature_flags
  key, country_code, is_enabled, config_json

მაგ: ('shop', 'GE', true), ('shop', 'AM', false),
     ('nutrition', 'GE', false), ('league', '*', true)
```

კლიენტი ფლაგებს იღებს `/config`-იდან გაშვებისას და ქეშავს.

### 19.2 XP → ფასდაკლების ვალუტა

v2-ში XP გახდება შოპის ფასდაკლების ვალუტა: **1000 XP = 5 ₾**, ჭერი შეკვეთის 20%.

ამისთვის v1-ში საჭიროა:
- `xp_ledger` append-only (უკვე გათვალისწინებულია)
- `xp_spent` ცალკე ledger-ად, არა ბალანსის ველად
- XP-ის ვადა: 12 თვე (ინფლაციის კონტროლი) — `occurred_at`-ის მიხედვით ითვლება

### 19.3 v3 კვება — რეკომენდაცია

მზა კვების **საკუთარი წარმოება არ არის რეკომენდებული**: HACCP, ცივი ჯაჭვი, ყოველდღიური ლოგისტიკა, 30–40% ჩამოწერის რისკი, მარჟა 15–20% სოფტის 80%-ის ნაცვლად. ერთი უხარისხო პარტია აზიანებს მთელი აპლიკაციის ბრენდს.

**რეკომენდებული მოდელი:** მარკეტპლეისი არსებული თბილისური meal-prep მიმწოდებლებით, 15–20% კომისიით. საკუთარ სამზარეულოზე გადასვლა მხოლოდ თვეში 500+ სტაბილური შეკვეთის შემდეგ.

---

## 20. რელიზის გეგმა

### ეტაპი 0 — მომზადება (2 კვირა)
რეპოზიტორიები, CI/CD, Apple/Google ანგარიშები, დიზაინ-სისტემა, კონტენტის ლიცენზიების აუდიტი

### ეტაპი 1 — ბირთვი (5 კვირა)
Laravel API + Filament ადმინი • მონაცემთა სქემა • auth • სავარჯიშოების ბიბლიოთეკა (40 სავარჯიშო) • ვარჯიშის პლეიერი • ოფლაინ SQLite + სინქი

### ეტაპი 2 — გეო + XP (3 კვირა)
PostGIS • მოედნების რუკა • check-in • XP ძრავა • streak • **200 მოედნის ხელით შევსება**

### ეტაპი 3 — Soft launch (3 კვირა)
დახურული ბეტა 100–200 მომხმარებელზე (TestFlight + Play Internal Testing) • თბილისის calisthenics კომუნიტი და ინსტაგრამ-გვერდები • კოეფიციენტების კალიბრაცია რეალურ მონაცემებზე

### ეტაპი 4 — სრული v1 (4 კვირა)
პროგრამები (3 ტრეკი × 3 დონე) • 150 სავარჯიშო • ლიგები (გააქტიურდება 200 MAU-ზე) • გაზიარების ბარათები • ტრენერების ლისტინგი • რუსული ლოკალიზაცია

### ეტაპი 5 — საჯარო გაშვება
App Store + Google Play • PR ქართულ ტექ და სპორტ მედიაში • ინსტაგრამ-კამპანია ლოკალურ ატლეტებთან

**სულ: ~17 კვირა (≈4 თვე) part-time რეჟიმში.**

> **სტრატეგიული რჩევა:** ეტაპი 3-ის შემდეგ (მოედნების რუკა + 40 სავარჯიშო + XP) პროდუქტი უკვე ღირებულია და გასაშვებია. ლიგები და სრული პროგრამები მას შემდეგ დაამატე, როცა რეალური მომხმარებლების ქცევას დაინახავ. სრული v1-ის ერთბაშად გაშვება ზრდის რისკს, რომ 4 თვე დაიხარჯოს ფუნქციებზე, რომლებიც არავის სჭირდება.

---

## 21. რისკები

| # | რისკი | ალბათობა | გავლენა | შემარბილებელი |
|---|---|---|---|---|
| R1 | ცარიელი რუკა გაშვებისას | მაღალი | კრიტიკული | 200 მოედანი ხელით, გაშვებამდე |
| R2 | ლიგა ცოტა მომხმარებლით | მაღალი | მაღალი | ლიგები გააქტიურდება 200 MAU-ზე |
| R3 | კონტენტის ლიცენზიის პრეტენზია | დაბალი | კრიტიკული | მხოლოდ საკუთარი და გასუფთავებული წყაროები |
| R4 | XP-ის ბალანსი არასწორია | მაღალი | საშუალო | append-only ledger, `k_snapshot`, გადათვლა |
| R5 | ჩიტინგი ლიდერბორდზე | საშუალო | საშუალო | verification tiers + ევრისტიკები |
| R6 | გადავარჯიშება / ტრავმა | საშუალო | მაღალი | დღიური ჭერი, დასვენების ლოგიკა, დისკლეიმერი |
| R7 | რუსულენოვანი სეგმენტი მცირდება | მაღალი | დაბალი | v1 ქართულ სეგმენტზეა აწყობილი |
| R8 | Scope creep (შოპი/კვება v1-ში) | მაღალი | მაღალი | feature flags, მკაცრი v1 სკოუპი |
| R9 | v1-ს შემოსავალი არ აქვს | დარწმუნებული | — | დაგეგმილია; ტრენერების ლისტინგი ერთადერთი წყარო |
| R10 | მედიის ზომა > 200 MB | საშუალო | საშუალო | H.265, delta sync, on-demand ჩამოტვირთვა |

---

## დანართი A — Laravel მიგრაციის ესკიზი (ძირითადი ცხრილები)

```php
Schema::create('exercises', function (Blueprint $t) {
    $t->id();
    $t->string('slug')->unique();
    $t->string('category', 32);
    $t->enum('force', ['push','pull','static','legs','core']);
    $t->enum('unit', ['reps','seconds']);
    $t->decimal('difficulty_coef', 5, 2);
    $t->unsignedTinyInteger('level_min')->default(1);
    $t->unsignedTinyInteger('level_max')->default(5);
    $t->json('equipment')->nullable();
    $t->json('primary_muscles');
    $t->json('secondary_muscles')->nullable();
    $t->foreignId('progression_from_id')->nullable()->constrained('exercises');
    $t->foreignId('progression_to_id')->nullable()->constrained('exercises');
    $t->string('skill_group', 32)->nullable();
    $t->boolean('is_skill_unlock')->default(false);
    $t->unsignedInteger('unlock_bonus_xp')->default(0);
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});

Schema::create('xp_ledger', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('session_id')->nullable()->constrained('workout_sessions');
    $t->foreignId('exercise_id')->nullable()->constrained();
    $t->string('reason', 24);
    $t->decimal('raw_value', 10, 2)->nullable();
    $t->decimal('k_snapshot', 5, 2)->nullable();
    $t->json('multipliers')->nullable();
    $t->integer('xp');
    $t->timestamp('occurred_at');
    $t->timestamp('created_at');
    $t->index(['user_id', 'occurred_at']);
});

// PostGIS — raw statement
DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
DB::statement('ALTER TABLE spots ADD COLUMN location GEOGRAPHY(POINT, 4326)');
DB::statement('CREATE INDEX idx_spots_location ON spots USING GIST (location)');
```

## დანართი B — ახლომდებარე მოედნების მოთხოვნა

```sql
SELECT s.id, s.name, s.condition_rating,
       ST_Distance(s.location, ST_MakePoint(:lng, :lat)::geography) AS distance_m,
       COUNT(c.id) FILTER (WHERE c.checked_in_at > NOW() - INTERVAL '3 hours') AS active_now
FROM spots s
LEFT JOIN spot_checkins c ON c.spot_id = s.id
WHERE s.status = 'verified'
  AND ST_DWithin(s.location, ST_MakePoint(:lng, :lat)::geography, :radius)
GROUP BY s.id
ORDER BY distance_m ASC
LIMIT 50;
```

## დანართი C — XP-ის გამოთვლის სერვისი (ფსევდოკოდი)

```php
public function calculate(WorkoutSession $session): int
{
    $total = 0;
    $dailyRepCount = $this->dailyRepsByExercise($session->user_id, $session->started_at);

    foreach ($session->sets as $set) {
        $ex = $set->exercise;
        $k  = $ex->difficulty_coef;

        $base = $ex->unit === 'reps'
            ? $k * $set->reps
            : $k * ($set->seconds / 5);

        $mods = [
            'weight' => 1 + ($set->added_weight_kg / $session->user->profile->weight_kg),
            'tempo'  => $set->tempo === 'slow' ? 1.2 : 1.0,
            'streak' => $this->streakMultiplier($session->user),
            'checkin'=> $session->spot_checkin_id ? 1.15 : 1.0,
            'dim'    => ($dailyRepCount[$ex->id] ?? 0) > 100 ? 0.3 : 1.0,
        ];

        $xp = (int) round($base * array_product($mods));

        XpLedger::create([
            'user_id'     => $session->user_id,
            'session_id'  => $session->id,
            'exercise_id' => $ex->id,
            'reason'      => 'workout',
            'raw_value'   => $set->reps ?? $set->seconds,
            'k_snapshot'  => $k,          // ← კრიტიკული
            'multipliers' => $mods,
            'xp'          => $xp,
            'occurred_at' => $set->completed_at,
        ]);

        $total += $xp;
        $dailyRepCount[$ex->id] = ($dailyRepCount[$ex->id] ?? 0) + $set->reps;
    }

    return min($total, $this->remainingDailyCap($session->user, $session->started_at));
}
```

---

**დოკუმენტის ბოლო.** ცვლილებები აღირიცხება git-ში; ვერსიის ნომერი იზრდება სკოუპის ან მონაცემთა მოდელის ცვლილებისას.
