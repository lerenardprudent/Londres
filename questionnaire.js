var Q = new function()
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
    }).fail(function() {
      alert( "Could not parse JSON" );
});
    
    return json;
  }
  
  this.cont = this.readJSON('includes/questionnaire.json');
  this.get = function(key) {
    if ( key in this.config ) {
      return this.config[key];
    }
    alert("Config element '" + key + "' not found!");
  };
}