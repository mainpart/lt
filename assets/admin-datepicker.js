jQuery(function ($) {

    $('input.paidtill').each(function (el) {
        //console.log($(this));
        $(this).daterangepicker({
            datepickerOptions:{maxDate:null}
        })
        var from = $(this).data('from')
        var to = $(this).data('to')
        if (from !=="" && to !==""){
            $(this).daterangepicker("setRange",
                {start:moment(from ).toDate(), end:moment(to).toDate() }
        )}


        $(this).on('change', function (e) {
            var container = $(this).closest("div[class^=paidtill]")
            var userid = $(e.target).closest('div[data-user-id]').data('user-id')
            var range = $(e.target).daterangepicker("getRange")
            var timeZoneOffset
            // var timeZone = range.start.getTimezoneOffset();
            //             if (timeZone > 0 ){
            //                 timeZoneOffset=-Math.abs(timeZone) * 60;
            //             }else {
            //                 timeZoneOffset=Math.abs(timeZone) * 60;
            //             }
            // console.log(timeZone, range.start.setHours(0,0,0,0)/1000)
		

                range.start = new Date(range.start.getTime() - ((new Date()).getTimezoneOffset() * 60000))
                range.end = new Date(range.end.getTime() - ((new Date()).getTimezoneOffset() * 60000))
		range.start.setHours(0,0,0,0)
		range.end.setHours(23,59,59,0)

		//console.log(range.start.toISOString());

            var ajax_data = {
                from: range ? range.start.toISOString() : '',
                to: range ? range.end.toISOString() : '',
                action: 'change_userdate_admin_ajax',
                nonce: my_ajax_object.nonce,
                userid: userid
            };
            var spinner = $('<div class="spinner"></div>');
            $(spinner).appendTo(container).css({visibility: 'visible', 'float': 'left'});
            container.find('span.paidtill').hide();


            $.ajax(
                {
                    type: "post",
                    url: my_ajax_object.ajaxurl,
                    data: ajax_data,
                    success:function(msg){
                        container.find('span.paidtill').show();
                        container.find('div.spinner').remove()
                    }
                });
        })
    })

});