=== Yandex Schema.org for WooCommerce ===
Contributors: uralgips
Tags: schema.org, woocommerce, yandex, microdata, seo, structured-data
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Генерирует микроразметку schema.org для WooCommerce согласно требованиям Яндекса.

== Описание ==

Плагин автоматически добавляет структурированную разметку schema.org в формате JSON-LD на все страницы интернет-магазина WooCommerce согласно требованиям Яндекса.

= Поддерживаемые типы разметки =

**Для страниц товаров (Product + Offer):**
* name — название товара (обязательно)
* description — описание товара (обязательно)
* image — изображения товара
* price — цена (обязательно)
* priceCurrency — валюта RUB (обязательно)
* availability — наличие товара
* url — ссылка на товар
* sku — артикул
* brand — бренд
* category — категория
* aggregateRating — рейтинг и отзывы
* weight — вес

**Для страниц каталога/категорий (OfferCatalog):**
* name — название каталога (обязательно)
* description — описание (обязательно)
* image — изображение (обязательно)
* itemListElement — список товаров (Offer[])

**Дополнительно:**
* Organization — информация об организации
* LocalBusiness (Store) — на главной странице
* WebSite — информация о сайте с поиском
* BreadcrumbList — навигационные цепочки

== Требования Яндекса ==

Плагин соответствует требованиям Яндекса для:
* [Информация о товарах](https://yandex.ru/support/webmaster/supported-schemas/goods-prices.html)
* [Строгая микроразметка — Товары](https://yandex.ru/support/webmaster/supported-schemas/strict-microdata-offers.html)
* [Строгая микроразметка — Каталоги](https://yandex.ru/support/webmaster/supported-schemas/catalogs.html)

== Установка ==

1. Загрузите папку `yandex-schema-woocommerce` в `/wp-content/plugins/`
2. Активируйте плагин через меню 'Плагины' в WordPress
3. Настройте данные организации в файле плагина (при необходимости)

== Проверка разметки ==

Для проверки корректности разметки используйте:
* [Валидатор микроразметки Яндекса](https://webmaster.yandex.ru/tools/microtest/)
* [Google Rich Results Test](https://search.google.com/test/rich-results)

== Настройка ==

Данные организации настраиваются в файле плагина в массиве `$this->organization`:

```php
$this->organization = array(
    'name' => 'Название компании',
    'telephone' => '+7 (XXX) XXX-XX-XX',
    'email' => 'email@example.com',
    'address' => array(
        'country' => 'RU',
        'region' => 'Регион',
        'city' => 'Город',
        'street' => 'Улица',
        'postal' => 'Индекс'
    ),
    // ...
);
```

== Changelog ==

= 2.1.0 =
* Вывод характеристик товаров (additionalProperty)
* Специальные свойства: color, material, size, model, pattern
* Автоматическое определение атрибутов WooCommerce
* Поддержка как таксономий, так и кастомных атрибутов

= 2.0.0 =
* Полноценная админ-панель с редактированием всех данных
* Поддержка вариативных товаров (AggregateOffer с lowPrice/highPrice)
* Разметка доставки (OfferShippingDetails)
* Разметка отзывов (Review)
* Мета-поля для товаров: GTIN, MPN, Бренд
* Размеры товара (length, width, height)
* Кеширование разметки
* Автоочистка кеша при обновлении товаров

= 1.0.0 =
* Первый релиз
* Поддержка Product + Offer для товаров
* Поддержка OfferCatalog для каталогов
* Organization, LocalBusiness, WebSite, BreadcrumbList
* Отключение стандартной разметки WooCommerce
* Страница настроек в админке
