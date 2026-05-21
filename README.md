# app-openvpn-watchdog

ClearOS Webconfig app for configuring and controlling **openvpn-watchdog**.

## Screenshot

![OpenVPN Watchdog Webconfig](docs/screenshots/openvpn-watchdog-webconfig.png)

## Scope

The app is intentionally conservative:

- edits only `/etc/openvpn-watchdog.conf`;
- manages `OPENVPN_PROFILES` through Add/Edit/Delete buttons instead of forcing raw array editing;
- creates backups before saving configuration;
- controls only `openvpn-watchdog.timer` through a fixed privileged helper;
- can run `openvpn-watchdog --dry-run` from Webconfig;
- can run one immediate watchdog check from Webconfig;
- does not directly start/stop/restart arbitrary OpenVPN profile units.

## Files

```text
controllers/      ClearOS Webconfig controllers
views/            summary and settings pages
libraries/        OpenVPN Watchdog integration logic
deploy/           install script and privileged helper
language/         translations
packaging/        RPM spec and local build helper
```

## Development install on ClearOS

```bash
sudo ./install-app-tree-dev.sh
```

Then open:

```text
/app/openvpn_watchdog
```

## Build RPM

```bash
./packaging/build-rpm.sh --nodeps
```

Install on ClearOS:

```bash
yum localinstall app-openvpn-watchdog-*.noarch.rpm
```

## Important safety notes

The **Run Check Now** button can trigger the normal openvpn-watchdog logic. If the watchdog detects a broken OpenVPN profile, it may restart only that affected OpenVPN service.

Use **Dry Run** first when testing new configuration.
