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
./bin/news news:fetch --ticker SBER
./bin/news news:cache
./bin/news news:search "нефть" --days=7
```

## Кэширование

- Структура: `cache/YYYY/MM/DD/Source/`
- Мета-данные: JSON с id, title, simhash, link, source, pubDate
- Поиск дубликатов: SimHash (расстояние Хэмминга ≤ 10)

## Поиск

- `--search` - по ключевым словам
- `--ticker` - по тикеру (расширяется до названий компании)
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
