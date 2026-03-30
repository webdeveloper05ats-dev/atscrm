function crmDataTable(tableId, options = {}) {

const $table = $(tableId);
if (!$table.length) return null;

// Global safety: if table contains a legacy custom empty row using colspan,
// clear it before DataTables init to avoid tn/18 incorrect column count errors.
let inferredEmptyText = "";
const $tbody = $table.find("tbody");
if ($tbody.length) {
const $rows = $tbody.find("tr");
if ($rows.length === 1) {
const $onlyRow = $rows.eq(0);
const $cells = $onlyRow.children("td,th");
const headerCols = $table.find("thead tr").first().children("th,td").length;
if ($cells.length === 1) {
const colspan = parseInt($cells.eq(0).attr("colspan") || "1", 10);
if (headerCols > 0 && colspan >= headerCols) {
inferredEmptyText = ($cells.eq(0).text() || "").replace(/\s+/g, " ").trim();
$tbody.empty();
}
}
}
}

let settings = {

pageLength: options.pageLength || 5,
lengthMenu: options.lengthMenu || [5,10,25,50],

autoWidth:false,
responsive:true,
scrollX:true,

dom:
options.export === false
? "<\"crm-table-header\"lf>" +
  "rt" +
  "<\"crm-table-footer\"ip>"
: "<\"crm-table-header\"lfB>" +
  "rt" +
  "<\"crm-table-footer\"ip>",

buttons:
options.export === false
? []
: [{
extend:'csvHtml5',
text:'Export CSV',
className:'crm-export-btn'
}],

language:{
search:"",
searchPlaceholder: options.searchPlaceholder || "Search...",
emptyTable: inferredEmptyText || "No records found."
}

};

settings = {...settings, ...options};
settings.language = {...settings.language, ...(options.language || {})};

return $table.DataTable(settings);

}
