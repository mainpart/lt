jQuery(function ($) {

    $('input.paidtill').each(function (el) {
        
        $(this).daterangepicker({
            datepickerOptions:{maxDate:null}
        })
        var from = $(this).data('from')
        var to = $(this).data('to')
        if (from !=="" && to !==""){
            $(this).daterangepicker("setRange",
                {
                    start:moment(from ).toDate(), 
                    end:moment(to).toDate() 
                }
        )}


        $(this).on('change', function (e) {
            var container = $(this).closest("div[class^=paidtill]")
            var userid = $(e.target).closest('div[data-user-id]').data('user-id')
            var range = $(e.target).daterangepicker("getRange")
            
            var timeZoneOffset
            var secondsFrom = moment().diff(moment().startOf('day'), 'seconds')

            var ajax_data = {
                from: range ? moment(range.start).add( secondsFrom, 'seconds').toDate().toISOString() : '',
                to: range ? moment(range.end).add( secondsFrom, 'seconds').toDate().toISOString() : '',
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