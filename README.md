# News CLI

CLI утилита для агрегации и поиска финансовых новостей из RSS-лент.

## Установка

```bash
composer require prikotov/news-core:@dev
```

## Использование

```bash
./vendor/bin/news news:fetch
./vendor/bin/news news:fetch --source=interfax --source=tass
./vendor/bin/news news:fetch --search "нефть" --ticker SBER
./vendor/bin/news news:fetch --category "Экономика" --limit=10

./vendor/bin/news news:cache
./vendor/bin/news news:search "Сбербанк" --days=7
```

## Команды

| Команда | Описание |
|---------|----------|
| `news:fetch` | Получить новости из RSS |
| `news:cache` | Загрузить и кэшировать |
| `news:search` | Поиск по кэшу |
| `news:cache-stats` | Статистика кэша |
| `news:sources` | Доступные источники |

## Источники

Interfax, TASS, RIA Novosti, PRIME, RBC, Kommersant

## Тикеры

SBER, GAZP, LKOH, NVTK, ROSN, GMKN, YNDX, VTBR, TCSG, MOEX и др.

## Разработка

```bash
composer install
composer test
composer cs-check
composer stan
composer psalm
```

## Лицензия

MIT
