# app-openvpn-watchdog 0.1.14 — profile helper + permission warning fix

Цей архів виправляє проблему, коли Webconfig не бачить OpenVPN client profiles через закриті права на `/etc/openvpn/client`.

## Що змінено

1. Webconfig більше не робить прямий `glob('/etc/openvpn/client/*-client.conf')`.
2. Список профілів читається через root-helper:

   ```bash
   /usr/sbin/clearos-openvpn-watchdog-helper list-profiles
   ```

3. Додано перевірку небезпечних прав:

   ```bash
   /usr/sbin/clearos-openvpn-watchdog-helper check-permissions
   ```

4. У Webconfig з'явиться попередження, якщо sensitive OpenVPN dirs/files мають доступ для `other`, наприклад `744`, `754`, `755`, `644`.
5. Додано кнопку **Виправити права OpenVPN**. Вона викликає root-helper і змінює тільки permissions, не owner/group.
6. Версію ClearOS app metadata оновлено до `0.1.14`.

## Як застосувати

```bash
unzip app-openvpn-watchdog-0.1.14-profile-permission-fix.zip
cd app-openvpn-watchdog-0.1.14-profile-permission-fix
./apply-openvpn-watchdog-0.1.14-fix.sh
```

## Що саме виправляє кнопка

Кнопка не змінює власника і групу. Вона тільки прибирає доступ для `other` на sensitive OpenVPN об'єктах:

- `/etc/openvpn/client` → `0750`
- `/etc/openvpn/server` → `0750`
- `/etc/openvpn/client/key*` → `0750`
- `/etc/openvpn/server/key*` → `0750`
- sensitive files `*.conf`, `*.ovpn`, `*.key`, `*.pem`, `*.crt`, `*.p12`, `*.pfx`, `*.pass`, `pass` → `0640`

Скрипти `client.up`, `client.down`, `*-restart.sh` навмисно не чіпаються, щоб нічого не зламати.

## Backup

Перед змінами скрипт робить backup у:

```text
/root/app-openvpn-watchdog-0.1.14-fix-backups/
```

## Перевірка вручну

```bash
/usr/sbin/clearos-openvpn-watchdog-helper list-profiles
/usr/sbin/clearos-openvpn-watchdog-helper check-permissions
sudo -u webconfig sudo -n /usr/sbin/clearos-openvpn-watchdog-helper list-profiles
```

Якщо `check-permissions` нічого не виводить — небезпечних прав не знайдено.


## Додатково у цій збірці

Виправлено змішаний переклад у попередженні про небезпечні права OpenVPN.
Рядки попередження, опису, кнопки та причин тепер записуються українською у `uk_UA` і `en_US`, бо на деяких ClearOS Webconfig може брати нові рядки з `en_US` навіть при українському інтерфейсі.

Приклад очікуваного тексту:

- **Небезпечні права OpenVPN**
- Інші користувачі мають доступ до конфігів або ключів OpenVPN. Це може розкрити VPN-налаштування або приватні ключі.
- `/etc/openvpn/clients.conf — права 644, рекомендовано 0640 (доступ other до конфіга або ключа)`
- **Виправити права OpenVPN**

## Packaging 0.1.14

У цьому архіві також виправлено packaging-файли для складання пакета:

- `files/packaging/app-openvpn-watchdog.spec`
- `files/deploy/info.php`

Щоб застосувати тільки packaging-файли у робочому дереві репозиторію:

```bash
cd /path/to/app-openvpn-watchdog
/path/to/app-openvpn-watchdog-0.1.14-packaging-fix/apply-packaging-0.1.14-fix.sh .
```

Або з каталогу розпакованого архіву:

```bash
./apply-packaging-0.1.14-fix.sh /path/to/app-openvpn-watchdog
```

Очікувана перевірка:

```bash
grep -E '^(Version|Release):' packaging/app-openvpn-watchdog.spec
grep "\$app\['version'\]" deploy/info.php
```

Очікувано:

```text
Version:        0.1.14
Release:        1%{?dist}
$app['version'] = '0.1.14';
```

## README.md

У цій збірці також оновлено основний `README.md` репозиторію під версію `0.1.14`.

Додано опис:

- виявлення профілів через privileged helper;
- перевірки небезпечних прав OpenVPN;
- кнопки виправлення прав;
- безпечної моделі, де `webconfig` не додається до групи `openvpn`;
- команд швидкої перевірки після встановлення.

Файл лежить у:

```text
files/README.md
```

Скрипт `apply-packaging-0.1.14-fix.sh` тепер копіює його у корінь репозиторію як:

```text
README.md
```
