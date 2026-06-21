=== Updatronix - Enhanced Update Manager ===
Contributors: quentinldd
Donate link: https://buymeacoffee.com/quentinld
Tags: updates, auto-update, maintenance, security, audit-log
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Confident WordPress updates, on your terms. Track every change, control all updates, and keep it private in wp-admin.

== Description ==

WordPress updates itself all the time, then forgets it ever happened. Updatronix remembers. It keeps a running record of every update on your site, and hands you the controls WordPress likes to tuck away: what updates on its own, and when.

One thing matters more than the rest. Updatronix builds on the WordPress update engine instead of swapping it out. Your settings are written to WordPress's own options, so your auto-update choices keep working even if you remove the plugin one day. I built it for the people who look after WordPress sites for a living, so it fits the way you already work.

= Who it's for =

* **Solo site owners.** Keep your site current and sleep at night. You'll always know what changed.
* **Freelancers.** When a client asks what you've been up to, the answer is right there.
* **Developers.** Real control over auto-updates and scheduling, plus the full detail behind every event.
* **Agencies.** The same update policy you trust, running on every client site, each with its own log.

== Features ==

Four tabs, one job each.

= Update logs =

Every update, written down. The moment WordPress installs something, whether it's core, a plugin, a theme, or a translation, it lands in the log. So when a site breaks a week later, you're not guessing about what changed. You can see it.

* The before and after version, what set it off, and how it ended.
* Filter by category, date, action, or user when the list gets long.
* Open any entry to read the details, including the exact error WordPress threw.

Need to hand it off? Export the log just as you've filtered it. Handy for briefing your team when something breaks, putting together a maintenance report for a client, or sending context to whoever you've called in to help.

= Auto-updates =

WordPress spreads its auto-update switches across half a dozen screens. This tab gathers them in one place. Flip what you want, and the choice goes straight into WordPress's own settings.

* Set core to every release, security and minor only, or fully manual.
* A switch for each plugin and theme, and one for translations.
* If a `wp-config.php` constant is overriding something, you'll see which one, in plain words.

= Schedule =

WordPress checks for updates twice a day, whenever it feels like it. Usually that's fine. When it isn't, this tab hands you the timing, and lets a new release age a little before it reaches you.

* Pick how often WordPress checks (hourly, twice daily, daily, or weekly) and the time of day.
* Hold automatic installs for up to 365 days after a release shows up.
* Skip the bad ones. If a plugin ships a broken update and then a quick fix, a short hold means you get the fix and miss the mess.
* Anyone logging in sees a notice while a hold is on, so nobody's left wondering.

= Settings =

The housekeeping. How long to keep your history, and which update emails actually reach you.

* Turn logging on or off, and keep entries anywhere from 1 to 365 days.
* Send WordPress's update emails wherever you want, to one inbox or a whole list.
* Already watching your sites another way? Switch the update emails off for good. (Recovery mode email stays on, so you can't lock yourself out.)

== Updatronix 3000 ==

Somewhere in a neon-lit server room, the next version is booting.

**Updatronix 3000** is the Pro edition, and it's almost online. Same core you already run, with extra firepower bolted on for developers and agencies who take their stack seriously.

* **Integrations tab.** Hooks and a REST API so you can plug Updatronix into your own pipeline. Trigger updates from the cloud, push events to your dashboard, automate the parts you'd rather not touch.
* **Update Shield.** A bouncer for your updates. It checks PHP compatibility, flags plugins that look abandoned, and notices a `.git` folder so a stray update can't clobber your work.
* **Update Flow.** Set the order your plugins update in, so the ones that lean on each other stop falling over.
* **White Label.** Your name on it, not mine. Clients see your agency, and the engine stays out of sight.

**Coming soon.** One payment, one site, yours to keep, with updates for life. I'm not a fan of subscriptions, so there won't be one. You buy, you own it.

== Privacy ==

Nothing leaves your site. No analytics, no telemetry, no third-party calls—Updatronix reads from WordPress, writes to your site's storage, and that's the whole network footprint. Delete the plugin and the log table goes with it, along with the settings.

== Accessibility ==

Updatronix aims to be fully accessible to all of its users. If you run into a problem—a missing label, a control you can't reach, anything that gets in your way, open a support thread on the plugin page and it'll get fixed.

== Multisite Support ==

On a multisite network, Updatronix is network-only. Network-activate it once, and a Super Admin manages everything from the Network Admin dashboard: the settings, the update history, the schedule, and the email controls are all network-wide and shared across every site. Individual sites show no Updatronix menu, settings page, or notice. Single-site installs are unaffected and behave exactly as described above.

== Screenshots ==

1. Update logs tab. The chronological view, with status, trigger, and version change for every entry.
2. Update logs tab. Filtering controls in action.
3. Update logs tab. A single entry expanded to show its detail.
4. Update logs tab. Deleting a single log entry.
5. Auto-updates tab. Core, plugins, themes, and translations on one screen.
6. Schedule tab. Recurrence, time of day, and the hold-for-N-days setting.
7. Settings tab. Retention, email routing, and the disable-all-emails switch.

== Installation ==

1. Search for "Updatronix" in **Plugins → Add New**, or upload the plugin files to `/wp-content/plugins/updatronix/`.
2. Activate the plugin from the Plugins screen.
3. Open **Tools → Updatronix** (or **Dashboard → Update logs**) to see the history and adjust the settings.

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but leaves your data alone. Deletion removes everything: the log table and the settings. On multisite, network-activate the plugin from the Network Admin Plugins screen; its data lives at the network level, and deletion clears it once for the whole network (including any leftover per-site data from earlier versions).

== Frequently Asked Questions ==

= Where do I see the history of updates on my site? =

Open **Tools → Updatronix**. The first tab is your log: date, item, version change, outcome. Click a row for the full entry. Logging is on from the day you activate, so the history builds itself.

= Can I export my update log? =

Yes. Filter the log how you like, then hit **Export logs**. You get a clean report you can drop into an email to your team, a maintenance summary for a client, or a note to whoever you've called in to help.

= How do I send WordPress update emails to a different address? =

In **Settings**, turn on **Manage update notifications** and enter an address (or several, separated by commas). Pick the events you care about and save. WordPress still sends the same emails. They just go where you want them now.

= Can I turn off WordPress update notification emails completely? =

Yes. Switch on **Disable all update notification emails** in **Settings** and they stop. The one exception is recovery mode email, the one that gets you back in after a fatal error. That stays on, on purpose. Getting locked out of your own site isn't a feature.

= Can I delay automatic updates? =

Yes. In **Schedule**, turn on **Hold automatic updates** and choose how many days to wait, up to 365. The clock runs per release, so a seven-day hold means an update is at least a week old before it installs. While a hold is on, the Updates, Plugins, and Themes screens show a notice so nobody's caught off guard.

= Does Updatronix work with my plugins, themes, and host? =

Almost certainly. Updatronix rides on the same update system WordPress already uses, so anything that comes through **Dashboard → Updates** or the automatic updater gets logged. WordPress.org, a private repo, your host's own mirror, it's all the same to it. And if your host or `wp-config.php` has locked something, Updatronix tells you what, so you're not poking at a switch that does nothing.

= Can Updatronix undo a failed update? =

No, and that's on purpose. Rolling back is a different job with its own risks, so Updatronix leaves it alone. What it does do is grab the error and the version details the second an update fails. That's the part you (or your developer) actually need to put things right.

= Where does my data go? =

Nowhere. Your logs and settings sit on your site and stay there. Updatronix doesn't make calls of its own. The only things it talks to are the APIs WordPress was already going to call.

= How long are log entries kept? =

However long you want. Set the window between 1 and 365 days in **Settings**, and a daily cleanup clears the rest. The default is 90 days, which works for most sites. Push it higher for a longer trail, or lower if storage is tight.

= Does Updatronix work on multisite? =

Yes, as a network-only plugin. A Super Admin turns it on for the whole network and runs everything from the Network Admin dashboard. The settings and history are shared across every site, the sites themselves stay clean, and single-site installs behave just like above.

== Changelog ==

= 1.1 =
* Change: Multisite is now network-only. Network-activate Updatronix and manage it as a Super Admin from the Network Admin dashboard; the settings and update history are shared network-wide, and individual sites no longer show any Updatronix interface. Single-site installs are unchanged.
* Change: On Multisite, the Network Admin update history and exports now show entries from every site on the network by default; deleting a site removes its entries from the shared history. Single-site installs are unchanged.
* i18n: Removed the per-site "schedule changes were not saved because they affect every site on this network" warning notice, which no longer applies under the network-only model.
* i18n: Save buttons now use "Save Changes" to match native WordPress settings screens.
* Improvement: Activity log DataViews toolbar includes a compact Export logs control (upload icon, minimal style; wiring TBD).
* Change: Streamlined the Update logs export modal — removed the optional "Details to include in each line" toggles; exports keep the standard date, category, status, and action details. Filter handling now flows from a single shared registry.
* Change: Redesigned the export report — merged exports group entries by category (Core, Plugins, Themes, Translations) and sort by date within each section. Non-merged exports use a single flat list sorted by date with a Category column. Each section or list includes aligned column headings with a dash separator. Translation rows show the package slug instead of the display name. The export modal adds copy buttons for formatted and plain-text clipboard output.
* Change: Clearer export modal wording — the "Heading table" column option is now "Column headings", and the modal intro explains in plain language that filters you have not set include all values.
* New: Schedule tab — pick how often WordPress checks for updates (every hour, twice a day, daily, or weekly) and a preferred time of day.
* New: Hold automatic updates for a chosen number of days after they appear, with a friendly notice on the Updates, Plugins, and Themes screens explaining the wait.
* New: One-click switch in Settings to turn off all WordPress update notification emails (recovery mode emails are kept on so you never get locked out).
* Improvement: Cleaner copy, smoother flow, and a focused accessibility pass across every tab; the WordPress "Automatic update not scheduled" message now stays in sync with your chosen schedule.
* Fix: Keyboard and screen-reader users can move focus into activity log modals.

= 1.0.6.1 =
* Fix: Readme.txt text is now naturally wrapped.

= 1.0.6 =
* Change: Align plugin lifecycle with WordPress uninstall expectations.
* Fix: Nested document landmarks for accessibility.
* Fix: Automatic update failures now record the real WP_Error (e.g. filesystem unavailable) in log details, not only generic upgrader messages.
* Change: Update component dependencies and remove hardcoded values in style to use design-token.css instead.
* Change: Update readme.txt content, tone, and voice.
* i18n: Align plugin interface with WordPress interface tone and voice, improving accessibility.

= 1.0.5 =
* Fix: Wire js script translations for bundled files.
* Add: Load theme/plugin descriptions translated into the current admin language for the auto-update panel.
* Add: Translation for "Icon", "Success", "Error", and "Warning" labels.
* Change: Cache the merged Jed/JSON translation inline payload.

= 1.0.4 =
* Fix: Wire js script translations.
* Fix: Wrong logging behavior for minor core auto-update. Was logged as "Reinstall" instead of "Update".
* Change: Code-split the admin JavaScript bundle with lazy-loaded tab modules to keep all emitted chunks below Webpack's recommended size limit and improve wp-admin load performance.
* Change: Align user interface standards to WP 7.0.
* Change: Tested up to WP 7.0-RC2.
* Change: Update screenshots for WordPress.org.
* Change: Update of the logo and banners.

= 1.0.3 =
* Add: Updatronix release on WordPress.org.

= 1.0.2 =
* Fix: Close `ob_start()` buffers safely.

= 1.0.1 =
* Fix: Better handling of updates logging.
* Change: Improve responsive, and plugin global UX.
* Change: Improve the readme.txt.

= 1.0 =
* Add: Initial release of Updatronix.

== Upgrade Notice ==

= 1.1 =
Adds the new Schedule tab to control when WordPress checks for updates and to delay automatic installs, plus a one-click switch to turn off every WordPress update notification email when you don't need them.

= 1.0.6 =
Improves uninstall cleanup, accessibility, and error logging for failed auto-updates.

= 1.0.5 =
Fixes script translations and adds translation caching for the admin interface.

= 1.0.4 =
Fixes core auto-update logging and aligns the interface with WordPress 7.0.
