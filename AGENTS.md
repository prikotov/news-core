# Инструкции для AI-агента

## Описание

CLI утилита для агрегации и поиска финансовых новостей из RSS-лент.

## Технологии

- PHP 8.4+
- Symfony Console, DependencyInjection
- Guzzle HTTP Client
- Monolog

## Источники RSS

- Interfax, TASS, RIA, PRIME, RBC, Kommersant

## Структура

```
├── bin/news
├── cache/YYYY/MM/DD/Source/
├── config/
├── src/
│   ├── Command/
│   ├── Service/News/, Cache/
│   └── Component/Rss/
└── tests/
```

## Команды

```bash
./bin/news news:search                    # fetch + cache + show recent
./bin/news news:search "Сбербанк"         # fetch + cache + search
./bin/news news:search "нефть" --days=7   # search last 7 days
./bin/news news:search --no-fetch         # search only in cache
./bin/news news:sources                   # list available sources
```

### Опции news:search

| Опция | Описание |
|-------|----------|
| `query` | Поисковые термины (аргумент) |
| `--source` | Фильтр по источникам |
| `--category` | Фильтр по категориям |
| `--days` | Сколько дней искать назад (default: 7) |
| `--limit` | Лимит результатов (default: 50) |
| `--format` | Формат вывода: md, json, csv, text |
| `--no-fetch` | Не обновлять кэш, только искать |

## Форматы вывода

- `md` (по умолчанию) - Markdown таблица
- `json` - JSON с полями query, total, items
- `csv` - CSV с заголовком
- `text` - ASCII-таблица

```bash
./bin/news news:search "Сбер" --format json
```

## Кэширование

- Структура: `cache/YYYY/MM/DD/Source/`
- Мета-данные: JSON с id, title, simhash, link, source, pubDate
- Поиск дубликатов: SimHash (расстояние Хэмминга ≤ 10)

## Архитектура

1. **Command** - форматирование вывода
2. **Service** - бизнес-логика, кэширование
3. **Component** - RSS парсинг

## Разработка

```bash
composer cs-check && composer stan && composer psalm && composer test
```

## Стиль кода

- `declare(strict_types=1);`
- readonly-свойства в DTO
- Без комментариев
