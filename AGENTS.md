# Инструкции для AI-агента

## Описание проекта

News Skill - консольное приложение на PHP для агрегации, кэширования и поиска новостей из RSS-лент.

## Технологии

- PHP 8.4+
- Symfony Console (CLI)
- Symfony DependencyInjection (DI)
- Guzzle HTTP Client
- Monolog (логирование)
- PHPUnit (тестирование)

## Источники RSS

- Interfax: https://www.interfax.ru/rss.asp
- TASS: https://tass.ru/rss/v2.xml
- RIA Novosti: https://ria.ru/export/rss2/archive/index.xml
- PRIME: https://1prime.ru/export/rss2/archive/index.xml
- RBC: https://rssexport.rbc.ru/rbcnews/news/30/full.rss
- Kommersant: https://www.kommersant.ru/RSS/news.xml

## Структура проекта

```
├── bin/news                     # Точка входа CLI
├── cache/                       # Локальный кэш новостей
│   └── YYYY/MM/DD/Source/       # Структура по датам и источникам
├── config/
│   ├── container.php            # Инициализация DI-контейнера
│   └── services.yaml            # Определение сервисов
├── src/
│   ├── Command/                 # Консольные команды
│   ├── Service/
│   │   ├── News/                # Бизнес-логика новостей
│   │   │   └── Dto/             # View-DTO для команд
│   │   └── Cache/               # Кэширование
│   └── Component/Rss/           # RSS-компоненты
│       └── Dto/                 # DTO для RSS данных
├── tests/                       # Тесты PHPUnit
├── .env                         # Конфигурация (в репозитории)
└── .env.local                   # Локальные переопределения (игнорируется git)
```

## Команды

### Установка зависимостей
```bash
composer install
```

### Тесты
```bash
composer test
```

### Код-стайл
```bash
composer cs-check    # Проверка PSR-12
composer cs-fix      # Исправление нарушений PSR-12
```

### Статический анализ
```bash
composer stan        # PHPStan
composer psalm       # Psalm
```

### Запуск приложения
```bash
# Получение новостей
./bin/news news:fetch
./bin/news news:fetch --source=interfax --source=tass
./bin/news news:fetch --search "нефть" --search "золото"
./bin/news news:fetch --ticker SBER --ticker GAZP
./bin/news news:fetch --category "Экономика" --category "Бизнес"
./bin/news news:fetch --limit=10 --format=json

# Кэширование
./bin/news news:cache                    # Загрузить и кэшировать новости
./bin/news news:cache --source=interfax  # Кэшировать только Interfax
./bin/news news:cache --clear=30         # Очистить кэш старше 30 дней

# Поиск по кэшу
./bin/news news:search "Сбербанк"
./bin/news news:search "нефть" --category "Экономика"
./bin/news news:search "" --source=interfax --days=3

# Другое
./bin/news news:cache-stats              # Статистика кэша
./bin/news news:sources                  # Доступные источники
./bin/news --help
```

## Кэширование

### Структура кэша
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

### Мета-данные (.json)
```json
{
    "id": "9980b22f",
    "title": "Заголовок новости",
    "title_norm": "заголовок новости",  // Нормализованный для поиска
    "simhash": "-5729861611271749632",  // SimHash для поиска дубликатов
    "link": "https://...",
    "source": "Interfax",
    "pubDate": "2026-03-17 16:19:00",
    "categories": ["Экономика"],
    "tags": []
}
```

### Поиск дубликатов
- **SimHash** - нечёткое сравнение текстов (расстояние Хэмминга ≤ 10)
- **title_norm** - нормализованное сравнение заголовков

## Поиск по новостям

### Поиск по ключевым словам (`--search`)
- Поиск по заголовку, описанию и категории
- Регистронезависимый
- Можно указать несколько терминов

### Поиск по тикерам (`--ticker`, `-t`)
- Автоматически расширяет тикер до названий компании
- Пример: `--ticker SBER` → ищет "Сбербанк", "Sber", "SBER"
- Поддерживаемые тикеры: SBER, GAZP, LKOH, NVTK, ROSN, GMKN, YNDX, VTBR, TCSG, MOEX и др.

### Поиск по категориям (`--category`, `-c`)
- Фильтрация по категории новости
- Пример: `--category "Экономика"`

```bash
# Примеры поиска
./bin/news news:fetch --search "нефть"
./bin/news news:fetch --search "золото" --search "доллар"
./bin/news news:fetch -t SBER -t GAZP
./bin/news news:fetch --category "В мире"
```

## Архитектура

### Трёхуровневая архитектура

1. **Command** - консольные команды, только форматирование вывода
2. **Service** - бизнес-логика, подготовка данных для команд
3. **Component** - парсинг RSS, HTTP-запросы

### Слой сервисов
```
src/Service/
├── News/
│   ├── Dto/                      # View-DTO для команд
│   ├── NewsServiceInterface.php  # Интерфейс
│   └── NewsService.php           # Реализация
└── Cache/
    ├── CacheServiceInterface.php # Интерфейс
    └── CacheService.php          # Реализация с SimHash
```

### RSS-компоненты
- `RssParserInterface.php` - Интерфейс
- `RssParser.php` - Реализация с Guzzle HTTP Client
- `Dto/RssFeedDto.php` - DTO ленты
- `Dto/RssItemDto.php` - DTO элемента новости

### Dependency Injection
- Все сервисы регистрируются в `config/services.yaml`
- Autowiring включён
- URL источников привязываются через параметры

## При внесении изменений

### Добавление нового источника RSS
1. Добавить URL в `.env`
2. Добавить параметр в `config/services.yaml`
3. Добавить свойство в `NewsService`
4. Добавить источник в массив `SOURCES`

### Добавление новой консольной команды
1. Создать команду в `src/Command/`
2. Команда автоматически зарегистрируется через тег `console.command`

### Добавление нового тикера
1. Открыть `src/Command/NewsFetchCommand.php`
2. Добавить тикер и его варианты названий в метод `getTickerNames()`
3. Формат: `'TICKER' => ['Название RU', 'Название EN', 'TICKER']`

### Перед коммитом
1. `composer cs-check` - исправить нарушения
2. `composer stan` - исправить ошибки PHPStan
3. `composer psalm` - исправить ошибки Psalm
4. `composer test` - убедиться что тесты проходят

### Стиль кода
- Без комментариев в коде, если явно не запрошено
- Использовать `declare(strict_types=1);`
- Использовать readonly-свойства в DTO
- Использовать возможности PHP 8.4+ (enums, readonly, property hooks и т.д.)
