# LUMN Utilities

A WordPress plugin providing a set of custom shortcodes, social URL redirects, and site management tools for LUMN client sites.

---

## Table of Contents

1. [Installation](#installation)
2. [Admin Settings](#admin-settings)
3. [Shortcodes](#shortcodes)
   - [Business Information](#business-information)
   - [Business Address](#business-address)
   - [Business Hours](#business-hours)
   - [Social URLs](#social-urls)
   - [SVG Icons](#svg-icons)
   - [Copyright & Year](#copyright--year)
4. [Social URL Redirects](#social-url-redirects)
5. [Maintenance Checklist](#maintenance-checklist)
6. [Google Sheets Integration](#google-sheets-integration)

---

## Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```
   wp-content/plugins/lumn-utilities/
   ```
2. In the WordPress admin, go to **Plugins** and activate **LUMN Utilities**.
3. The **LUMN Utilities** settings menu will appear in the left sidebar.

The plugin auto-updates from the `main` branch of this GitHub repository using the included Plugin Update Checker library.

---

## Admin Settings

Navigate to **LUMN Utilities** in the WordPress admin sidebar. Settings are organized into tabbed sections:

| Section | Purpose |
|---|---|
| General Business Information | Site name, phone, text, fax, email |
| Business Address | Street address, city, state, zip, Google Maps embed |
| Business Hours | Hours for each day of the week |
| Social Links | Social media and utility URLs |
| Other Shortcodes | Reference guide for SVG icons, copyright, year shortcodes |

All changes are saved with the **Save Changes** button within each section.

---

## Shortcodes

All shortcodes that output text accept an optional `html_tag` attribute that wraps the output in an HTML element.

**Valid `html_tag` values:** `p`, `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `span`, `div`, `strong`, `em`, `i`, `b`

---

### Business Information

Set values under **LUMN Utilities > General Business Information**.

| Shortcode | Description |
|---|---|
| `[lumn_site_name]` | Outputs the business/site name |
| `[lumn_call]` | Outputs the phone number |
| `[lumn_txt]` | Outputs the text/SMS number |
| `[lumn_fax]` | Outputs the fax number |
| `[lumn_email]` | Outputs the email address |

**Example:**
```
[lumn_site_name html_tag="h2"]
[lumn_call html_tag="p"]
[lumn_email]
```

---

### Business Address

Set values under **LUMN Utilities > Business Address**.

#### Combined Address Shortcode

```
[lumn_address]
```

Outputs the full address, formatted across multiple lines by default.

| Attribute | Description | Values | Default |
|---|---|---|---|
| `singleline` | Display address on one line | `true`, `false` | `false` |
| `html_tag` | Wrap in an HTML tag | See valid tags above | none |

**Examples:**
```
[lumn_address]
[lumn_address singleline="true"]
[lumn_address singleline="true" html_tag="p"]
```

#### Individual Address Shortcodes

| Shortcode | Description |
|---|---|
| `[lumn_address_street]` | Street address line 1 |
| `[lumn_address_street2]` | Street address line 2 |
| `[lumn_address_city]` | City |
| `[lumn_address_state]` | State |
| `[lumn_address_zip]` | ZIP code |
| `[lumn_map]` | Outputs the Google Maps iframe embed |

**Example:**
```
[lumn_address_street html_tag="p"]
[lumn_map]
```

---

### Business Hours

Set values under **LUMN Utilities > Business Hours**.

#### Combined Hours Shortcode

```
[lumn_hours]
```

Outputs all business hours. Accepts multiple formatting options.

| Attribute | Description | Values | Default |
|---|---|---|---|
| `format` | Output format | `list`, `table`, (blank = plain text) | blank |
| `abbreviate` | Abbreviate day names (Mon, Tue...) | `true`, `false` | `false` |
| `grouped` | Group consecutive days with identical hours | `true`, `false` | `false` |
| `hide_closed` | Hide days with no hours or "Closed" | `true`, `false` | `false` |
| `html_tag` | Wrap in an HTML tag (plain text only) | See valid tags above | none |

**Examples:**
```
[lumn_hours format="table"]
[lumn_hours format="list" abbreviate="true" grouped="true" hide_closed="true"]
[lumn_hours html_tag="p"]
```

#### Individual Day Shortcodes

One shortcode per day. Each accepts `html_tag`.

```
[lumn_hours_monday]
[lumn_hours_tuesday]
[lumn_hours_wednesday]
[lumn_hours_thursday]
[lumn_hours_friday]
[lumn_hours_saturday]
[lumn_hours_sunday]
```

**Example:**
```
[lumn_hours_monday html_tag="p"]
```

---

### Social URLs

Set values under **LUMN Utilities > Social Links**.

#### Social URL Shortcode

Returns the URL stored for the specified social platform.

```
[lumn_social_url name="platform"]
```

| Attribute | Description | Example Values |
|---|---|---|
| `name` | The platform identifier | See list below |

**Available platform names:**

| Name | Platform |
|---|---|
| `appointments` | Appointments booking link |
| `payments` | Payments link |
| `facebook` | Facebook |
| `google` | Google Maps / Business |
| `instagram` | Instagram |
| `linkedin` | LinkedIn |
| `pinterest` | Pinterest |
| `threads` | Threads |
| `tiktok` | TikTok |
| `x` | X (formerly Twitter) |
| `yelp` | Yelp |
| `youtube` | YouTube |

**Example:**
```
[lumn_social_url name="facebook"]
[lumn_social_url name="google"]
```

---

### SVG Icons

The plugin ships with a set of predefined SVG icons and can also output any SVG from the media library.

```
[lumn_svg]
```

| Attribute | Description | Example |
|---|---|---|
| `name` | Name of a predefined SVG file (without `.svg` extension) | `name="phone"` |
| `src` | Full URL path to any SVG file on the server | `src="/wp-content/uploads/icon.svg"` |

**Examples:**
```
[lumn_svg name="phone"]
[lumn_svg name="lumn-fish"]
[lumn_svg src="/wp-content/uploads/2024/01/custom-icon.svg"]
```

A visual reference of all predefined icons is available under **LUMN Utilities > Other Shortcodes**.

---

### Copyright & Year

#### Copyright

Outputs a formatted copyright line including the site name, current year, and LUMN attribution.

```
[lumn_copyright]
[lumn_copyright html_tag="p"]
```

Output example: `Acme Co © 2025 | Propelled by LUMN`

#### Year

Outputs the current four-digit year.

```
[lumn_year]
[lumn_year html_tag="span"]
```

---

## Social URL Redirects

In addition to shortcodes, each social URL has a corresponding redirect path that can be used in contexts where shortcodes are not accepted (e.g., menu items, QR codes).

**Pattern:** `/lumn-social-url-{platform}`

**Examples:**

| URL | Redirects to |
|---|---|
| `/lumn-social-url-facebook` | Stored Facebook URL |
| `/lumn-social-url-google` | Stored Google URL |
| `/lumn-social-url-appointments` | Stored appointments URL |
| `/lumn-social-url-payments` | Stored payments URL |

The full list mirrors the platform names listed in the Social URLs section above.

---

## Maintenance Checklist

The Maintenance Checklist is an internal tool for LUMN team members to track recurring and one-time site maintenance tasks.

### Access

Only users with the `company_super_admin` role can see and use this feature. The **Maintenance** menu item is hidden from all other roles.

### Using the Checklist

1. Go to **Maintenance** in the WordPress admin sidebar.
2. A table lists all maintenance tasks with their current status.
3. Check the checkbox next to a task to mark it complete. The change is saved instantly — no page reload required.
4. Uncheck a task to mark it incomplete.

### Row Color Coding

| Color | Meaning |
|---|---|
| Green | Task is complete |
| Red | Task is overdue (interval has elapsed) |
| Yellow | Task is due within 7 days |
| White | Task is pending (not yet due) |

### Task List

| Task | Frequency |
|---|---|
| Update plugins | Monthly |
| Remove unused plugins | Monthly |
| Verify reCAPTCHA is active | Monthly |
| Verify forms have valid email addresses | Monthly |
| Verify forms have reply-to configured | Monthly |
| Verify Formidable spam filters | Monthly |
| Verify LUMN Security Plugin installed & updated | Monthly |
| Run WordFence scan | Monthly |
| Update admin email to dev@getlumn.com | One-time |
| Update copyright | Yearly |
| Check console log for errors | Monthly |

### Auto-Reset Logic

- **Monthly tasks** automatically reset to incomplete 30 days after they were last checked.
- **Yearly tasks** reset after 365 days.
- **One-time tasks** (e.g., "Update admin email") never reset once completed.
- If a task has never been checked, it is shown as overdue immediately.

---

## Google Sheets Integration

The plugin can send maintenance status data from every WordPress site to a central Google Sheet via a webhook. This gives your team a single dashboard showing the maintenance health of all client sites.

### Data Sent Per Sync

| Field | Description |
|---|---|
| Site URL | The WordPress site's URL |
| Total Tasks | Total number of maintenance tasks |
| Incomplete Tasks | Number of tasks not yet completed |
| Oldest Unchecked Task | Date of the oldest task that hasn't been checked |
| Last Updated | Timestamp of the sync |

### Sync Triggers

Data is synced:
- **Automatically** whenever a task is checked or unchecked on the Maintenance page.
- **Daily** via a scheduled background job (WP-Cron).

### Setup Guide

#### Step 1: Create the Google Sheet

1. Go to [Google Sheets](https://sheets.google.com) and create a new spreadsheet.
2. Rename the first sheet tab to `Maintenance`.
3. Add these headers in Row 1:

   | A | B | C | D | E |
   |---|---|---|---|---|
   | Site URL | Total Tasks | Incomplete Tasks | Oldest Unchecked Task | Last Updated |

#### Step 2: Add the Apps Script

1. In your spreadsheet, go to **Extensions > Apps Script**.
2. Delete any existing code.
3. Copy the entire contents of `/docs/google-apps-script.js` from this repository and paste it.
4. Find this line and replace the placeholder with a strong secret string:
   ```javascript
   var API_KEY = 'YOUR_SECRET_API_KEY_HERE';
   ```
   Keep a copy of this key — you'll need it in WordPress.

#### Step 3: Deploy the Web App

1. Click **Deploy > New deployment**.
2. Click the gear icon next to "Select type" and choose **Web app**.
3. Configure:
   - **Execute as:** Me
   - **Who has access:** Anyone
4. Click **Deploy**.
5. Copy the **Web App URL** shown after deployment.

#### Step 4: Configure the WordPress Plugin

1. In WordPress, go to **Maintenance** in the sidebar.
2. Scroll down to **Google Sheets Integration Settings**.
3. Paste the Web App URL into **Google Script Webhook URL**.
4. Enter the same API key you set in the script into **API Key**.
5. Click **Save Settings**.

#### Step 5: Verify

Check a task on the Maintenance page. Within a few seconds, a row for this site should appear (or update) in your Google Sheet.

---

*Plugin maintained by [LUMN](https://getlumn.com).*
