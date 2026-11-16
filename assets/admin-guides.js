/**
 * Admin Guides — sortable blocks, add/remove, color picker, media picker.
 */
(function ($) {
  'use strict';

  /* ── Sortable ─────────────────────────────────────────── */
  $(function () {

    $('#cv-tg-blocks-list').sortable({
      handle: '.cv-tg-drag-handle',
      placeholder: 'cv-tg-sortable-placeholder',
      update: reindex,
    });

    // Add block button
    $(document).on('click', '.cv-tg-add-block', function () {
      var type = $(this).data('type');
      var tpl  = $('#cv-tg-block-templates [data-tpl="' + type + '"]').html();
      if (!tpl) return;
      var count = $('#cv-tg-blocks-list .cv-tg-block-row').length;
      tpl = tpl.replace(/__IDX__/g, count);
      var $row = $(tpl);
      $('#cv-tg-blocks-list').append($row);
      initRow($row);
      reindex();
      // Scroll to new block
      $('html,body').animate({ scrollTop: $row.offset().top - 60 }, 300);
    });

    // Remove block
    $(document).on('click', '.cv-tg-block-remove', function () {
      $(this).closest('.cv-tg-block-row').slideUp(200, function () {
        $(this).remove();
        reindex();
      });
    });

    // Toggle collapse
    $(document).on('click', '.cv-tg-block-toggle', function () {
      var $fields = $(this).closest('.cv-tg-block-row').find('.cv-tg-block-fields');
      var $btn    = $(this);
      $fields.slideToggle(150, function () {
        $btn.text($fields.is(':visible') ? '▼' : '▶');
      });
    });

    // Init existing rows
    $('#cv-tg-blocks-list .cv-tg-block-row').each(function () {
      initRow($(this));
    });

    // Color pickers
    $('.cv-color-picker').wpColorPicker();

    // Media picker (background image on guide design panel)
    $(document).on('click', '.cv-tv-media-btn', function (e) {
      e.preventDefault();
      var targetId    = $(this).data('target');
      var targetInput = $(this).data('target-input');
      var $input      = targetId ? $('#' + targetId) : $(this).prev('input[type="url"]');

      var frame = wp.media({ title: 'Scegli immagine', button: { text: 'Usa questa immagine' }, multiple: false });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $input.val(att.url);
      });
      frame.open();
    });
  });

  function initRow($row) {
    // nothing special per-row for now
  }

  function reindex() {
    $('#cv-tg-blocks-list .cv-tg-block-row').each(function (i) {
      $(this).find('input, select, textarea').each(function () {
        var name = $(this).attr('name');
        if (name) {
          $(this).attr('name', name.replace(/cv_tg_blocks\[\d+\]/, 'cv_tg_blocks[' + i + ']'));
        }
      });
    });
  }

}(jQuery));
