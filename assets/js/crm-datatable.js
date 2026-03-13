function crmDataTable(tableId, options = {}){

let settings = {

pageLength: options.pageLength || 5,
lengthMenu: options.lengthMenu || [5,10,25,50],
autoWidth:false,

dom: options.dom || 
"<\"crm-table-header\"lfB>" +
"rt" +
"<\"crm-table-footer\"ip>",

buttons:[
{
extend:'csvHtml5',
text:'Export CSV',
className:'crm-export-btn'
}
],

language:{
search:"",
searchPlaceholder: options.searchPlaceholder || "Search..."
}

};

$(tableId).DataTable(settings);

}