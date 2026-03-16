function crmDataTable(tableId, options = {}){

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
searchPlaceholder: options.searchPlaceholder || "Search..."
}

};

/* ⭐ MERGE USER OPTIONS */

settings = {...settings, ...options};

/* RETURN DATATABLE INSTANCE */

return $(tableId).DataTable(settings);

}