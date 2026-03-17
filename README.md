# News Skill

Консольное приложение на PHP для агрегации и поиска новостей из RSS-лент.

## Установка

```bash
composer install
```

## Использование

```bash
# Получить новости
./bin/news news:fetch
./bin/news news:fetch --source=interfax --source=tass
./bin/news news:fetch --search "нефть" --search "золото"
./bin/news news:fetch --ticker SBER --ticker GAZP
./bin/news news:fetch --category "Экономика" --category "Бизнес"
./bin/news news:fetch --limit=10 --format=json

# Кэширование
./bin/news news:cache                    # Загрузить и кэшировать новости
./bin/news news:cache --clear=30         # Очистить кэш старше 30 дней
./bin/news news:cache-stats              # Статистика кэша

# Поиск по кэшу
./bin/news news:search "Сбербанк"
./bin/news news:search "нефть" --category "Экономика"
./bin/news news:search "" --source=interfax --days=3
```

## Команды

| Команда | Описание |
|---------|----------|
| `news:fetch` | Получить новости из RSS источников |
| `news:cache` | Загрузить и кэшировать новости |
| `news:search` | Поиск по локальному кэшу |
| `news:cache-stats` | Статистика кэша |
| `news:sources` | Показать доступные источники |

## Опции news:fetch

| Опция | Описание |
|-------|----------|
| `--source`, `-s` | Указать источники (interfax, tass, ria, prime, rbc, kommerzant) |
| `--search` | Поиск по ключевым словам в заголовке, описании и категории |
| `--ticker`, `-t` | Поиск по тикерам акций (автоматически расширяется до названий компаний) |
| `--category`, `-c` | Фильтрация по категориям (Экономика, Бизнес, В мире и т.д.) |
| `--limit`, `-l` | Ограничить количество новостей (по умолчанию 50) |
| `--format`, `-f` | Формат вывода: table, json, simple (по умолчанию table) |

## Опции news:search

| Опция | Описание |
|-------|----------|
| `query` | Поисковый запрос (позиционный аргумент) |
| `--source`, `-s` | Фильтр по источникам |
| `--category`, `-c` | Фильтр по категориям |
| `--days`, `-d` | Количество дней для поиска (по умолчанию 7) |
| `--limit`, `-l` | Ограничить количество результатов (по умолчанию 50) |
| `--format`, `-f` | Формат вывода: table, json, simple |

## Кэширование

Структура кэша:
```
cache/
└── 2026/
    └── 03/
        └── 17/
            ├── Interfax/
            │   ├── 20260317-161900-9980b22f.json  # Мета-данные
            │   └── 20260317-161900-9980b22f.txt   # Текст новости
            └── TASS/
                └── ...
```

**Мета-данные (.json):**
- id, title, title_norm (нормализованный заголовок)
- simhash (для поиска дубликатов)
- link, source, pubDate
- categories, tags

**Поиск дубликатов:**
- SimHash с расстоянием Хэмминга ≤ 10
- Нормализованное сравнение заголовков

## Поддерживаемые тикеры

| Тикер | Названия для поиска |
|-------|---------------------|
| SBER | Сбербанк, Sber, SBER |
| GAZP | Газпром, Gazprom, GAZP |
| LKOH | Лукойл, Lukoil, LKOH |
| NVTK | Новатэк, Novatek, NVTK |
| ROSN | Роснефть, Rosneft, ROSN |
| GMKN | Норникель, Nornickel, GMKN, Норильский никель |
| YNDX | Яндекс, Yandex, YNDX |
| VTBR | ВТБ, VTB, VTBR |
| TCSG | Т-Банк, Тинькофф, Tinkoff, TCSG |
| MOEX | Мосбиржа, Moscow Exchange, MOEX |
| ... | и другие |

## Источники

- Interfax
- TASS
- RIA Novosti
- PRIME
- RBC
- Kommersant

## Разработка

```bash
composer test       # Запуск тестов
composer cs-check   # Проверка PSR-12
composer cs-fix     # Исправление PSR-12
composer stan       # PHPStan
composer psalm      # Psalm
```

## Лицензия

MIT
