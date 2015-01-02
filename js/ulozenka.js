function selPobocka(num) {
   
      
   var el =document.getElementById('selulozenka');
   var sel=el.options[el.selectedIndex].value;
 
   if(sel) {
    pobockaSelected=1;
   }
   else {
     pobockaSelected=0;    
   }


$.ajax({
  url: baseDir + 'modules/ulozenka/ulozenka-ajax.php',
  type: "POST",
  data: { selpobocka : sel },
   dataType: 'json',
})
.success(function( data ) {
    
    if(data['onepageactive'] == 1)
         var prices=$('td.carrier_price');
    else if(data['version'] == 160)
        var prices=$('td.delivery_option_price');
    else
        var prices=$('.delivery_option_price');
       
   $(prices[num]).text(data['cena']);
   if (data['refresh'] == 1) {
       if(data['opc'] ==1 && ulozenkaActive) {
         $("#HOOK_PAYMENT").html(data['platba'] );
       }
   
    if(data['allow'] == 1)
      $("#pobockadetail").show();  
   else
      $("#pobockadetail").hide();
   } 
   if(data['onepageactive'])
       updateCarrierSelectionAndGift();
});


}  




function fbox() {
  var el =document.getElementById('selulozenka');
  var sel=el.options[el.selectedIndex].value;

  if(sel) {
  var url = baseDir  + 'modules/ulozenka/pobocka.php?code='+sel;
    $.fancybox({
        type: 'iframe',
        href: url,
        'width':500,
        'height':500,
    });
  }
}



