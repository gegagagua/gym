# Kalisteni API

Laravel 13 (PHP 8.3) · PostgreSQL 17 + PostGIS 3.6 · Redis 7 · Filament 5

## API რუკა

Base: `/api/v1` · Auth: `Authorization: Bearer <sanctum-token>` · `Accept-Language: ka|ru|en`

### საჯარო

```
GET    /health
GET    /config                      feature flags per country + ლიმიტები
GET    /cities
GET    /attributions                კონტენტის ლიცენზიები (ავტო-გენერირებული)

POST   /auth/otp/request            { phone }                 · 3/სთ/ნომერზე
POST   /auth/otp/verify             { phone, code }
POST   /auth/social                 { provider, provider_id }
POST   /auth/guest                  { device_uuid }

GET    /exercises                   ?force&category&level&equipment&updated_since&q
GET    /exercises/{id}
GET    /programs                    ?track&level
GET    /programs/{id}
GET    /content/manifest            ?since — დელტა-სინქი ჰეშებით
GET    /spots/nearby                ?lat&lng&radius&equipment[]
GET    /spots/{id}
GET    /trainers                    ?spot_id&city_id
```

### ავტორიზებული

```
GET    /me                          PATCH /me · PATCH /me/profile
POST   /me/level-test               { pushup, pullup, plank_sec } → დონე + რეკომენდაცია
GET    /me/stats                    ?period=week|month|all
GET    /me/records
GET    /me/checkin                  მიმდინარე აქტიური check-in

POST   /sessions/sync               ოფლაინ პარტია (max 20) · 60/სთ
GET    /sessions

GET    /league/current
GET    /leaderboard/friends | /spot/{id} | /city/{id} | /records/{exerciseId}

POST   /spots                       UGC (multipart) · 10/დღეში
POST   /spots/{id}/checkin          { lat, lng, accuracy_m }
POST   /spots/{id}/rating

POST   /share/card                  GET /share/card/{id}
POST   /me/program/advance          POST /programs/{id}/enroll · GET /me/program

POST   /auth/refresh | /auth/upgrade · DELETE /auth/logout | /account
```

## XP-ის გამოთვლა

```
reps:  base = k × გამეორებები
hold:  base = k × (წამები / 5)

final = base × weight × tempo × streak × checkin × diminishing
```

| მამრავლი | წყარო |
|---|---|
| `weight` | `1 + added_kg / bodyweight`, ჩაჭრილი [1.0, 2.0] |
| `tempo` | `slow` = 1.2 |
| `streak` | 3+ = 1.05 · 7+ = 1.10 · 14+ = 1.15 · 30+ = 1.25 |
| `checkin` | ვერიფიცირებულ მოედანზე GPS check-in = 1.15 |
| `diminishing` | იმავე დღეს იმავე სავარჯიშოს 100 გამეორების შემდეგ = 0.3 |

ლიმიტები `config/kalisteni.php`-შია. დღიური ჭერი (1800 XP) **სეტების
დონეზე** იჭრება, რომ `xp_ledger`-ის ჯამი ყოველთვის ტოლი იყოს დარიცხულის —
წინააღმდეგ შემთხვევაში აუდიტი ირღვევა.

Skill unlock ბონუსები ჭერს არ ექვემდებარება: ისინი თითო სავარჯიშოზე
სიცოცხლეში ერთხელ გაიცემა, ანუ არ ფარმდება, და მათი ჩაჭრა მხოლოდ
პროდუქტს დააზიანებდა.

## ვერიფიკაციის დონეები

| Tier | პირობა | რაში ითვლება |
|---|---|---|
| T0 | ხელით შეყვანილი / clock skew | პირადი სტატისტიკა; ლიგაში მაქს. 300 XP/დღე |
| T1 | სესია აპში, ტაიმერით | სრული კვირეული XP |
| T2 | T1 + GPS check-in ვერიფიცირებულ მოედანზე | +15%, მოედნის ბორდი |
| T3 | ვიდეო-PR, მოდერირებული | all-time PR ბორდი |

## Scheduler

```
ორშ 00:05 Asia/Tbilisi   RotateLeagues              კვირის დახურვა + ახლის ფორმირება
ყოველ 5 წუთში            RecomputeLeagueStandings   Redis ← ledger
ყოველ საათში             SendStreakRiskPush         20:00 ლოკალურად, თუ streak ≥ 3
03:00 Asia/Tbilisi       PurgeDeletedAccounts       30-დღიანი grace გასული ანგარიშები
```

გაშვება: `php artisan schedule:work` + `php artisan horizon`.

## ტესტები

```bash
createdb kalisteni_test
psql -d kalisteni_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
php artisan test
```

56 ტესტი. sqlite განზრახ არ გამოიყენება — `ST_DWithin`, `ST_Distance` და
`percentile_cont` მასზე არ არსებობს, ანუ გეო-ლოგიკა და ანტი-ჩიტი
დაუფარავი დარჩებოდა.
