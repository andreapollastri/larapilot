# Filament — static screens

Open `index.html` for the catalog. Every screen links `../tokens.css` only, has no script, no web font and
no remote asset, and follows the OS theme (`dashboard-dark.html` forces dark).

Sample product: **Northwind Ops** (orders `NW-10483…NW-10492`, customers such as Harbor Lane Café,
Kettle & Crumb, Saffron & Salt; products such as the Ceramic Pour-Over Dripper). Keep the same data when
extending a screen.

Screens: dashboard, widgets-dashboard, dashboard-dark, resource-list, filters, resource-create,
resource-edit, resource-view, relation-manager, form-wizard, modal, slide-over, notifications,
empty-state, settings, profile, cards-navigation (cluster sub-navigation), components, login,
auth-register. See `../README.md` for what each shows and the PHP it maps to.
