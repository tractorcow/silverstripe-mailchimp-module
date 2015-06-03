(function($){
    
    $(document).ready(function(){
       
       $('a.ajax-call').live("click", function(){
           $.ajax({
               type : "POST",
               url : $(this).attr("href"),
               data : {},
               dataType : 'json',
               success : function(data, status, xhr) {
                   alert("Updated Successfully. Please Reload Your Page.");
               },
               error : function(xhr, status, errObj) {
                   alert("Error: Status = " + status);
               }    
           });
           return false; 
       });
               
    });

})(jQuery);