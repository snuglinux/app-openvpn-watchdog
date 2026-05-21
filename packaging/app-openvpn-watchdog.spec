Name:           app-openvpn-watchdog
Version:        0.1.12
Release:        1%{?dist}
Summary:        ClearOS OpenVPN Watchdog web interface

License:        GPLv3
URL:            https://github.com/snuglinux/app-openvpn-watchdog
Source0:        https://github.com/snuglinux/app-openvpn-watchdog/archive/refs/tags/%{version}.tar.gz

BuildArch:      noarch
Requires:       app-base
Requires:       app-base-core
Requires:       openvpn-watchdog >= 0.2.0
Requires:       sudo
Requires(post): systemd

%description
app-openvpn-watchdog provides a ClearOS Webconfig page for editing
/etc/openvpn-watchdog.conf, viewing configured OpenVPN profiles, running a dry
run, running an immediate watchdog check, and controlling the
openvpn-watchdog.timer systemd timer.

%prep
%setup -q -n %{name}-%{version}

%build
# Nothing to build.

%install
rm -rf %{buildroot}

install -d -m 0755 %{buildroot}/usr/clearos/apps/openvpn_watchdog

if [ -d apps/openvpn_watchdog ]; then
    cp -a apps/openvpn_watchdog/. %{buildroot}/usr/clearos/apps/openvpn_watchdog/
elif [ -d openvpn_watchdog ]; then
    cp -a openvpn_watchdog/. %{buildroot}/usr/clearos/apps/openvpn_watchdog/
elif [ -d controllers ] && [ -d libraries ] && [ -d views ] && [ -d deploy ]; then
    cp -a controllers libraries views deploy %{buildroot}/usr/clearos/apps/openvpn_watchdog/
    [ -d htdocs ] && cp -a htdocs %{buildroot}/usr/clearos/apps/openvpn_watchdog/
    [ -d language ] && cp -a language %{buildroot}/usr/clearos/apps/openvpn_watchdog/
else
    echo "ERROR: Cannot find ClearOS app-openvpn-watchdog source layout." >&2
    exit 1
fi

if [ -f %{buildroot}/usr/clearos/apps/openvpn_watchdog/deploy/install ]; then
    chmod 0755 %{buildroot}/usr/clearos/apps/openvpn_watchdog/deploy/install
else
    echo "ERROR: missing deploy/install" >&2
    exit 1
fi

if [ -f %{buildroot}/usr/clearos/apps/openvpn_watchdog/deploy/openvpn-watchdog-helper.sh ]; then
    chmod 0755 %{buildroot}/usr/clearos/apps/openvpn_watchdog/deploy/openvpn-watchdog-helper.sh
else
    echo "ERROR: missing deploy/openvpn-watchdog-helper.sh" >&2
    exit 1
fi

install -d -m 0755 %{buildroot}/var/clearos/openvpn_watchdog
install -d -m 0755 %{buildroot}/usr/sbin
install -d -m 0750 %{buildroot}/etc/sudoers.d

install -m 0755 %{buildroot}/usr/clearos/apps/openvpn_watchdog/deploy/openvpn-watchdog-helper.sh %{buildroot}/usr/sbin/clearos-openvpn-watchdog-helper

cat > %{buildroot}/etc/sudoers.d/clearos-openvpn-watchdog <<'EOF'
# ClearOS OpenVPN Watchdog app helper
Defaults!/usr/sbin/clearos-openvpn-watchdog-helper !requiretty
Defaults!/usr/sbin/clearos-openvpn-watchdog-helper lecture=never
webconfig ALL=(root) NOPASSWD: /usr/sbin/clearos-openvpn-watchdog-helper *
apache ALL=(root) NOPASSWD: /usr/sbin/clearos-openvpn-watchdog-helper *
nobody ALL=(root) NOPASSWD: /usr/sbin/clearos-openvpn-watchdog-helper *
EOF
chmod 0440 %{buildroot}/etc/sudoers.d/clearos-openvpn-watchdog

%post
/bin/sh /usr/clearos/apps/openvpn_watchdog/deploy/install >/dev/null 2>&1 || :

%files
%defattr(-,root,root,-)
/usr/clearos/apps/openvpn_watchdog
%attr(0755,root,root) /usr/sbin/clearos-openvpn-watchdog-helper
%config(noreplace) %attr(0440,root,root) /etc/sudoers.d/clearos-openvpn-watchdog
%dir /var/clearos/openvpn_watchdog

%changelog
* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.12-1
- Keep version at 0.1.12 and make language files conservative for ClearOS/PHP 5.x.
- Keep Ukrainian UI text in both en_US fallback and uk_UA language files.
- Keep UI emojis out of language files.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.10-1
- Show readable event times without timezone suffix in the events table.
- Add a clear-events button with confirmation.
- Hide log/state directories from general settings while preserving current config values.
- Improve Ukrainian labels and help text.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.9-1
- Add severity emojis in event log table.
- Rename Watchdog version label and Internet ping targets label.
- Hide custom service column from read-only profile table.
- Use existing OpenVPN config filenames as profile-name dropdown options.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.7-1
- Add OpenVPN Watchdog app icon.
- Make event log table full width and include message column clearly.
- Improve Ukrainian translations and DataTables labels on the event page.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.6-1
- Hide the app information sidebar on the events page.
- Show the event message directly in the events table.
- Keep the events page table focused on Time, Severity, Profile, Type and Message.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.5-1
- Move Recent Events to a separate full-width page.
- Render structured watchdog events as a table.
- Add an events-page button after Edit on the summary page.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.2-1
- Hide low-level status/config fields from Webconfig summary and settings.

* Thu May 21 2026 SnugLinux <khvalera@ukr.net> - 0.1.0-1
- Initial ClearOS Webconfig app for openvpn-watchdog configuration and timer control.
