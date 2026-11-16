/**
 * Admin schedule — add/remove slot rows, toggle playlist/screen selects.
 */
(function ($) {
  'use strict';

  var slotCount = 0;

  function reindex() {
    $('#cv-tv-schedule-slots tr').each(function (i) {
      $(this).find('select, input').each(function () {
        var name = $(this).attr('name');
        if (name) {
          $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
        }
      });
    });
  }

  function bindRow(row) {
    row.find('.cv-tv-content-type-sel').on('change', function () {
      var val = $(this).val();
      var tr  = $(this).closest('tr');
      if (val === 'screen') {
        tr.find('.cv-tv-content-playlist').hide();
        tr.find('.cv-tv-content-screen').show();
      } else {
        tr.find('.cv-tv-content-playlist').show();
        tr.find('.cv-tv-content-screen').hide();
      }
    });

    row.find('.cv-tv-remove-slot').on('click', function () {
      $(this).closest('tr').remove();
      reindex();
    });
  }

  function addSlot() {
    var tpl  = document.getElementById('cv-tv-slot-template');
    if (!tpl) return;
    var html = tpl.innerHTML.replace(/__IDX__/g, slotCount++);
    var row  = $(html);
    bindRow(row);
    $('#cv-tv-schedule-slots').append(row);
    reindex();
  }

  $(function () {
    slotCount = $('#cv-tv-schedule-slots tr').length;

    // Bind existing rows
    $('#cv-tv-schedule-slots tr').each(function () {
      bindRow($(this));
    });

    $('#cv-tv-add-slot').on('click', addSlot);
  });
}(jQuery));
