jQuery(function ($) {

  // при нажатии на delete убираем кнопку
  $('.dupeable .delete').click(function(){
    $(this).closest('.dupeable').remove()
    return false
  })
  // при нажатии на добавление дублируем последнюю
  $('#addrow').click(function(){
    var $dupeable = $(this).closest('.dupeable_outer').find('.dupeable').last()
    var $container = $(this).closest('.dupeable_outer').find('.dupeable_container')
    console.log($dupeable, $container)
    var $clone = $dupeable.clone()
    $clone.find('input, select, textarea').each(function(){
      this.name = this.name.replace(/\[(\d+)\]/,function(str,p1){return '[' + (parseInt(p1,10)+1) + ']'});
    }).end().appendTo($container)

    return false
  })
})