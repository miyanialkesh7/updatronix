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

Enhanced Update Manager for WordPress. Monitor every change, controll all updates, fine tune your website maintenance flow.

== Description ==

WordPress, plugins and themes need regulary updates, but then forgets it ever happened. Updatronix remembers. It keeps a running record of every update on your website, and hands you more controls over update process: what updates, when and how.

Updatronix is precision plugin build on the native WordPress update engine instead of swapping it out. Your settings are written to WordPress's own options, so your auto-update choices keep working even if you remove the plugin one day. I built it for the people who look after WordPress sites for a living, so it fits the way you already work.

= Built for every users in their diversity of needs =

* **Solo site owners:** Keep your site up to date and sleep at night. You'll always know what changed.
* **Freelancers:** When a client asks what you've been up to, the answer is right there.
* **Developers:** Real control over auto-updates and scheduling, plus the full detail behind every event.
* **Agencies:** The same update policy you trust, running on every client site, each with its own log.

== Features ==

Four tabs, one per concern.

= Update logs =

Every core, plugin, theme, and translation update are logged with full details. If something breaks after updates, you have a trusty starting point instead of a guess.

* The before and after version, what set it off, and how it ended.
* Filter by category, date, action, or user when the list gets long.
* Open any entry to read the details, including the exact error WordPress threw.

Need to hand it off? Export the log just as you've filtered it. Handy for briefing your team when something breaks, sending a maintenance report for a client, or sharing context to someone you've called in to help.

= Auto-updates =

WordPress 5.5 made auto-updates available across the dashboard. The controls then got scattered across half a dozen screens. This tab pulls them onto one page: how core updates itself, which plugins and themes update on their own, whether translations come along.

* Set core to every release, security and minor only, or fully manual.
* A switch for each plugin and theme, and one for translations.
* If a `wp-config.php` constant is overriding something, you'll see which one.

= Schedule =

WordPress checks for updates twice a day. Usually that's fine. When it isn't, this feature hands you the timing, and lets a new release age a little before it reaches you.

* Pick how often WordPress checks (hourly, twice daily, daily, or weekly) and the time of day.
* Hold automatic installs after a release shows up.
* Skip the bad ones. If a plugin ships a broken update and then a quick fix, a short hold means you get the fix and miss the mess.
* A notice appears on the Updates, Plugins, and Themes screens whenever a hold is active.

= Settings =

How long the log sticks around, who gets the update emails WordPress sends, and the master switch for those emails when you'd rather not see them at all. That switch leaves recovery mode emails alone—being locked out of your own site is not a notification preference.

* Set up log conservation policy.
* Send WordPress's update emails to desired recipient.
* Per-event filters: core, plugin and theme, debug summary, technical alerts.
* One switch silences every WordPress update notification email recovery mode excepted to prevent self-lock.

== Updatronix 3000 ==

Somewhere in a neon-lit server room, the next version is booting.

**Updatronix 3000** is the Pro edition comming soon. Extending Updatronix, with additionnal features for for developpers, power users and agencies.

* **Developpers tools.** Hooks, functions and a REST API to plug Updatronix into your own pipeline. Push events to your own dashboards, trigger backups, automate maintenance reports, imagine wathever you want and improve your workflow with solid foundation. 
* **Update Shield.** Checks PHP compatibility, flags abandoned plugins, version control protection.
* **Update Flow.** You are the expert, you know your stack: set the exact order your plugins update in.
* **White Label.** Your name on it, not mine. Clients see your agency, and the engine stays out of sight.

**Coming soon.** One payment, one license, one site, with updates for life. I'm not a fan of subscriptions, so there won't be one. You buy it, you own it.

== Privacy ==

Nothing leaves your site. No analytics, no telemetry, no third-party calls—Updatronix reads from WordPress, writes to your site's storage, and that's the whole network footprint. Delete the plugin and the log table goes with it, along with the settings.

== Accessibility ==

Updatronix aims to be fully accessible to all of its users. If you run into a problem—a missing label, a control you can't reach, anything that gets in your way, open a support thread on the plugin page and it'll get fixed.

== Multisite Support ==

On a multisite network, Updatronix is network-only. Network-activate it once, and a Super Admin manages everything from the Network Admin dashboard: the settings, the update history, the schedule, and the email controls are all network-wide and shared across every site.

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

Open **Tools → Updatronix**. The first tab is the log: date, item, version change, outcome. Click any row to drill into a single entry. Logging is on by default after activation; if you've turned it off in the past, only events recorded while it was on will show up.

= Can I export my update log? =

Yes. Filter the log how you like, then click on **Export logs** button. You get a clean report you can drop into an email to your team, a maintenance summary for a client, or a note to whoever you've called in to help.

= How do I send WordPress update emails to a different address? =

In **Settings**, turn on **Manage update notifications** and put the address in the recipient field. A comma-separated list works if you want to send the emails to several inboxes. Pick which event types should trigger an email—core, plugin and theme, debug summary, technical alert—and save. WordPress keeps sending the same emails it always sends; they just go to the address you picked instead of the site admin.

= Can I turn off WordPress update notification emails completely? =

Yes. In **Settings**, turn on **Disable all update notification emails**. That suppresses the core, plugin, theme, and debug summary emails WordPress would normally send. Recovery mode emails—the ones that arrive after a fatal error so you can log back in—are deliberately exempt. Disabling those would lock you out of your own site, which is the opposite of helpful.

= Can I delay automatic updates? =

Yes. In **Schedule**, enable **Delay Updates** and set the number of days WordPress should wait after a release appears. The countdown is per release, not per check, so a 7-day hold means an offer is at least 7 days old before it installs. While anything is on hold, the Updates, Plugins, and Themes screens display a notice explaining what's happening.

= Does Updatronix work with my plugins, themes, and host? =

It hooks into the same update pipeline WordPress already runs, so anything that updates through **Dashboard → Updates** or the automatic update system gets logged—whether the package comes from WordPress.org, a private source, or your host's mirror. If your host or `wp-config.php` locks a setting from outside the dashboard, Updatronix surfaces a notice explaining what's locked, so you don't waste time wondering why a toggle isn't responding.

= Can Updatronix undo a failed update? =

No. Rolling updates back is a different problem with different tradeoffs, and Updatronix deliberately stays out of it. What it does instead: when an update fails, the plugin captures the WordPress error and the version snapshot before WordPress moves on. That's the data you need to recover by hand—or hand to your host's support so they can.

= Where does my data go? =

Nowhere. Logs and settings live on your site. The plugin makes zero outbound network calls of its own—every API it touches is one WordPress was already going to call without it.

= How long are log entries kept? =

Up to you. In **Settings**, set the retention window between 1 and 365 days. A daily cleanup task drops anything older. The default is 90 days, which works for most sites; raise it if you need a longer audit trail, or lower it if your hosting is tight on storage.

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
