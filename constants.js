var Consts = new function()
{
  this.readJSON = function(file)
  {
    var json;
    
    $.ajax({
      type: 'GET',
      async: false,
      beforeSend: function(xhr){
        if (xhr.overrideMimeType) {
          xhr.overrideMimeType("application/json");
        }
      },
      url: file,
      dataType: "json",
      success: function(data) {
        json = data;
      }
    });
    
    return json;
  }
  
  this.config = this.readJSON('includes/constants.json');
  this.get = function(key) {
    if ( key in this.config ) {
      return this.config[key];
    }
    alert("Config element '" + key + "' not found!");
  };
}