/**
 * LUMN Utilities - Google Apps Script Webhook
 * =============================================
 * Receives maintenance summary data from WordPress sites and writes/updates
 * a row in a Google Sheet for each site.
 *
 * SETUP INSTRUCTIONS
 * ------------------
 *
 * 1. CREATE THE GOOGLE SHEET
 *    - Go to https://sheets.google.com and create a new spreadsheet.
 *    - Name the first sheet "Maintenance" (or any name you prefer).
 *    - Add the following column headers in Row 1:
 *        A: Site URL
 *        B: Total Tasks
 *        C: Incomplete Tasks
 *        D: Oldest Unchecked Task
 *        E: Last Updated
 *
 * 2. OPEN THE APPS SCRIPT EDITOR
 *    - In your Google Sheet, go to Extensions > Apps Script.
 *    - Delete any existing code in the editor.
 *    - Paste the entire contents of this file.
 *
 * 3. SET YOUR API KEY
 *    - Find the line below: var API_KEY = 'YOUR_SECRET_API_KEY_HERE';
 *    - Replace the placeholder with a strong secret string.
 *    - This same key must be entered in the WordPress plugin's
 *      Maintenance > Google Sheets Integration Settings.
 *
 * 4. DEPLOY AS A WEB APP
 *    - Click Deploy > New deployment.
 *    - Click the gear icon next to "Select type" and choose "Web app".
 *    - Set the following:
 *        Description:      LUMN Maintenance Webhook (or any label)
 *        Execute as:       Me
 *        Who has access:   Anyone
 *    - Click Deploy.
 *    - Copy the Web App URL shown after deployment.
 *    - Paste this URL into the WordPress plugin:
 *        Maintenance > Google Sheets Integration Settings > Google Script Webhook URL
 *
 * 5. AUTHORIZE THE SCRIPT
 *    - On first deployment, Google will ask you to authorize the script.
 *    - Follow the prompts and grant access to your Google account.
 *
 * 6. TEST THE CONNECTION
 *    - Save your settings in WordPress.
 *    - Check a task on the Maintenance page.
 *    - Within a few seconds, a new row should appear (or be updated) in your sheet.
 *
 * NOTES
 * -----
 * - The sheet is matched by Site URL. Subsequent syncs update the existing row.
 * - All times are stored and displayed in UTC.
 * - If you re-deploy the script, update the webhook URL in WordPress.
 */

var API_KEY = 'YOUR_SECRET_API_KEY_HERE';

var SHEET_NAME = 'Maintenance';

var COLUMNS = {
    SITE_URL:       0,
    TOTAL_TASKS:    1,
    INCOMPLETE:     2,
    OLDEST_UNCHECKED: 3,
    LAST_UPDATED:   4,
};

/**
 * Entry point for HTTP POST requests from WordPress.
 *
 * @param  {GoogleAppsScript.Events.DoPost} e
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function doPost(e) {
    try {
        var payload = JSON.parse(e.postData.contents);

        if (!payload || payload.api_key !== API_KEY) {
            return jsonResponse({ success: false, error: 'Unauthorized' }, 403);
        }

        var siteUrl             = payload.site_url             || '';
        var totalTasks          = parseInt(payload.total_tasks, 10)          || 0;
        var incompleteTasks     = parseInt(payload.incomplete_tasks, 10)     || 0;
        var oldestUnchecked     = parseInt(payload.oldest_unchecked_task_timestamp, 10) || 0;
        var lastCheckedTimestamp = parseInt(payload.last_checked_timestamp, 10) || 0;

        if (!siteUrl) {
            return jsonResponse({ success: false, error: 'Missing site_url' }, 400);
        }

        var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
        if (!sheet) {
            return jsonResponse({ success: false, error: 'Sheet "' + SHEET_NAME + '" not found' }, 500);
        }

        var oldestDate    = oldestUnchecked     > 0 ? new Date(oldestUnchecked     * 1000) : '';
        var lastUpdatedDate = lastCheckedTimestamp > 0 ? new Date(lastCheckedTimestamp * 1000) : new Date();

        var existingRow = findRowBySiteUrl(sheet, siteUrl);

        if (existingRow > 0) {
            var range = sheet.getRange(existingRow, 1, 1, 5);
            range.setValues([[siteUrl, totalTasks, incompleteTasks, oldestDate, lastUpdatedDate]]);
        } else {
            sheet.appendRow([siteUrl, totalTasks, incompleteTasks, oldestDate, lastUpdatedDate]);
        }

        return jsonResponse({ success: true, message: 'Row updated for ' + siteUrl });

    } catch (err) {
        return jsonResponse({ success: false, error: err.message }, 500);
    }
}

/**
 * Find the row number (1-indexed) matching a given site URL.
 * Returns 0 if not found.
 *
 * @param  {GoogleAppsScript.Spreadsheet.Sheet} sheet
 * @param  {string} siteUrl
 * @return {number}
 */
function findRowBySiteUrl(sheet, siteUrl) {
    var lastRow = sheet.getLastRow();
    if (lastRow < 2) {
        return 0;
    }
    var urls = sheet.getRange(2, COLUMNS.SITE_URL + 1, lastRow - 1, 1).getValues();
    for (var i = 0; i < urls.length; i++) {
        if (urls[i][0] === siteUrl) {
            return i + 2;
        }
    }
    return 0;
}

/**
 * Build a JSON TextOutput response.
 *
 * @param  {Object} data
 * @param  {number} [statusCode] - unused by Apps Script but documented for clarity
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function jsonResponse(data) {
    return ContentService
        .createTextOutput(JSON.stringify(data))
        .setMimeType(ContentService.MimeType.JSON);
}
