/**
 * Admin playlist editor — jQuery UI sortable + add/remove rows.
 */
(function ($) {
  'use strict';

  var rowCount = 0;

  function reindex() {
    $('#cv-tv-playlist-items tr').each(function (i) {
      $(this).find('select, input').each(function () {
        var name = $(this).attr('name');
        if (name) {
          $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
        }
      });
    });
  }

  function addRow() {
    var tpl  = document.getElementById('cv-tv-row-template');
    if (!tpl) return;
    var html = tpl.innerHTML.replace(/__IDX__/g, rowCount++);
    var row  = $(html);
    row.find('.cv-tv-remove-row').on('click', function () {
      $(this).closest('tr').remove();
      reindex();
    });
    $('#cv-tv-playlist-items').append(row);
    reindex();
  }

  $(function () {
    // Count existing rows
    rowCount = $('#cv-tv-playlist-items tr').length;

    // Sortable
    $('#cv-tv-playlist-items').sortable({
      handle: '.cv-tv-drag-handle',
      placeholder: 'ui-state-highlight',
      stop: reindex,
    });

    // Add row
    $('#cv-tv-add-row').on('click', addRow);

    // Remove existing rows
    $(document).on('click', '.cv-tv-remove-row', function () {
      $(this).closest('tr').remove();
      reindex();
    });
  });
}(jQuery));
