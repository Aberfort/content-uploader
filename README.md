# Content Uploader

**Content Uploader** — плагін для WordPress, який дозволяє завантажувати та імпортувати контент з ZIP-архівів з підтримкою багатомовності WPML. Плагін генерує slug на основі заголовку, оновлює існуючі пости або створює нові, реєструє кастомні типи записів за потребою та зв'язує мовні версії постів за допомогою WPML.

## Вимоги.

- WordPress 5.0+
- WPML (WordPress Multilingual Plugin) для багатомовності
- PHP 7.0+
- Дозволені розширення PHP: `ZipArchive`, `mbstring`, `SimpleXML`

## Встановлення

1. Скопіюйте папку `content-uploader` до каталогу `wp-content/plugins/`.
2. Переконайтеся, що бібліотека [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) знаходиться в папці: wp-content/plugins/content-uploader/plugin-update-checker/ та що файл `plugin-update-checker.php` існує.
3. Активуйте плагін через адмінпанель WordPress.

## Налаштування та використання

## 1. Підготовка

1. Переконайтеся, що WPML налаштовано для роботи з мовами, наприклад, `uk` та `ru`, а також, що для кастомних типів записів встановлено перекладність:
- Увімкніть переклад для потрібних кастомних типів в налаштуваннях WPML: **WPML → Settings → Post Types Translation**.

## 2. Завантаження архіву

1. Перейдіть у адмінпанель WordPress і знайдіть меню **Content Uploader**.
2. На сторінці плагіна ви побачите форму для завантаження ZIP-архіву.
3. Натисніть "Choose File", оберіть `.zip` файл з контентом, і натисніть "Upload".

## 3. Формат ZIP-архіву та CSV

Архів повинен містити:
- Папки: `images/`, `files/`
- Файл `data.csv` в корені архіву з такими полями:
```csv
title,description,lang,post_type,image,file
Atilla Post Test,Description in Ukrainian,uk,post,atilla.jpg,content.txt
Atilla Post Test,Description in Russian,ru,post,atilla_ru.jpg,content_ru.txt
Football Match,Match description,uk,football_liga,match.jpg,match.txt
Football Match,Match description RU,ru,football_liga,match_ru.jpg,match_ru.txt
```
Обов'язкові поля в CSV: title, description, lang, post_type, image, file.

## 4. Кастомні типи записів та їх переклад
### Реєстрація кастомних типів:
- При першому завантаженні CSV з новими кастомним типами, плагін збирає їх, зберігає в опцію `content_uploader_cpt_types` і реєструє їх на ранньому етапі через хук `init`.
- Після реєстрації типів, плагін просить користувача увімкнути переклад цих типів у WPML і повторити завантаження файлу для завершення імпорту мовних версій.

### Дії для користувача:
1. Завантажте ZIP-файл, що містить нові кастомні типи.
2. Якщо з'явиться повідомлення про нові типи, перейдіть до WPML → Settings → Post Types Translation.
3. Увімкніть переклад для нових кастомних типів (наприклад, `football_liga`).
4. Повторно завантажте ZIP-файл, щоб імпортувати мовні версії для нових кастомних типів.

### Примітки:
- Після активації плагіна нові кастомні типи реєструються автоматично, якщо зберігаються в опції.
- WPML повинен бути налаштований для перекладу цих типів вручну через адмінпанель.
- Плагін використовує фільтр `wp_unique_post_slug` для уникнення створення суфіксів у slug для різних мов.

---