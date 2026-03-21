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
./bin/news news:fetch
./bin/news news:fetch --search "Сбербанк"
./bin/news news:search "нефть" --days=7
./bin/news news:cache
```

## Форматы вывода

Все команды поддерживают `--format`:
- `md` (по умолчанию) - Markdown таблица
- `json` - JSON массив объектов
- `csv` - CSV с заголовком
- `text` - ASCII-таблица

```bash
./bin/news news:fetch              # md
./bin/news news:fetch --format csv
```

## Кэширование

- Структура: `cache/YYYY/MM/DD/Source/`
- Мета-данные: JSON с id, title, simhash, link, source, pubDate
- Поиск дубликатов: SimHash (расстояние Хэмминга ≤ 10)

## Поиск

- `--search` - по ключевым словам
- `--category` - по категории
- `--days` - ограничение по дням

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
