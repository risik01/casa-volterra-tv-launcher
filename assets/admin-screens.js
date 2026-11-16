/**
 * Admin screens — Media Library picker + webcam row manager.
 */
(function ($) {
  'use strict';

  /* ── Media Library picker ─────────────────────────────────── */

  $(document).on('click', '.cv-tv-media-btn', function (e) {
    e.preventDefault();
    var targetId = $(this).data('target');
    if (!targetId) return;

    var frame = wp.media({
      title: 'Scegli file',
      button: { text: 'Usa questo file' },
      multiple: false,
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      $('#' + targetId).val(attachment.url);
    });

    frame.open();
  });

  /* ── Webcam rows ──────────────────────────────────────────── */

  function webcamReindex() {
    $('#cv-tv-webcam-rows tr.cv-tv-webcam-row').each(function (i) {
      $(this).find('input, select').each(function () {
        var name = $(this).attr('name');
        if (name) {
          $(this).attr('name', name.replace(/cv_tv_webcams\[\d+\]/, 'cv_tv_webcams[' + i + ']'));
        }
      });
    });
  }

  function addWebcamRow() {
    var tpl = document.getElementById('cv-tv-webcam-row-tpl');
    if (!tpl) return;
    var count = $('#cv-tv-webcam-rows tr.cv-tv-webcam-row').length;
    var html  = tpl.innerHTML.replace(/__IDX__/g, count);
    var $row  = $(html);
    $row.find('.cv-tv-webcam-remove').on('click', function () {
      $(this).closest('tr').remove();
      webcamReindex();
    });
    $('#cv-tv-webcam-rows').append($row);
    webcamReindex();
  }

  // Aggiorna placeholder campo URL in base al tipo scelto
  $(document).on('change', '.cv-tv-wc-type-sel', function () {
    var type  = $(this).val();
    var input = $(this).closest('tr').find('.cv-tv-wc-url-input');
    if (type === 'windy_id') {
      input.attr('placeholder', 'es. 1292947496');
    } else {
      input.attr('placeholder', 'https://...');
    }
  });

  $(function () {
    // Bind remove on existing rows
    $(document).on('click', '.cv-tv-webcam-remove', function () {
      $(this).closest('tr').remove();
      webcamReindex();
    });

    // Add row
    $('#cv-tv-webcam-add').on('click', addWebcamRow);
  });

}(jQuery));
