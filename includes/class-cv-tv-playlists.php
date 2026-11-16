<?php
/**
 * Playlists CPT — sequenza ordinata di Screens con durata e transizione per ognuna.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Playlists {

	const PT = 'cv_tv_playlist';

	public static function init() {
		add_action( 'init',        array( __CLASS__, 'register_post_type' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_gutenberg' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::PT, array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::PT,      array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts',       array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::PT . '_posts_columns',       array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'          => __( 'Playlist', 'casa-volterra-tv-launcher' ),
			'singular_name' => __( 'Playlist', 'casa-volterra-tv-launcher' ),
			'add_new'       => __( 'Aggiungi playlist', 'casa-volterra-tv-launcher' ),
			'add_new_item'  => __( 'Nuova playlist TV', 'casa-volterra-tv-launcher' ),
			'edit_item'     => __( 'Modifica playlist', 'casa-volterra-tv-launcher' ),
			'not_found'     => __( 'Nessuna playlist trovata', 'casa-volterra-tv-launcher' ),
			'menu_name'     => __( 'Playlist', 'casa-volterra-tv-launcher' ),
		);
		register_post_type(
			self::PT,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=cv_tv_screen',
				'supports'        => array( 'title' ),
				'show_in_rest'    => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function disable_gutenberg( $use, $post_type ) {
		if ( self::PT === $post_type ) {
			return false;
		}
		return $use;
	}

	public static function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::PT !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'cv-tv-admin-playlist',
			CV_TV_URL . 'assets/admin-playlist.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			CV_TV_VERSION,
			true
		);
		// Pass screens list for select
		$screens = get_posts( array(
			'post_type'      => 'cv_tv_screen',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'suppress_filters' => false,
		) );
		$screens_data = array();
		foreach ( $screens as $s ) {
			$screens_data[] = array( 'id' => $s->ID, 'title' => $s->post_title );
		}
		wp_localize_script( 'cv-tv-admin-playlist', 'cvTvScreens', $screens_data );
	}

	/* ── Meta boxes ─────────────────────────────────────────── */

	public static function register_meta_boxes() {
		add_meta_box(
			'cv_tv_playlist_items',
			__( '🎬 Schermate nella playlist', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_items_box' ),
			self::PT,
			'normal',
			'high'
		);
		add_meta_box(
			'cv_tv_playlist_settings',
			__( '⚙️ Opzioni riproduzione', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_settings_box' ),
			self::PT,
			'normal',
			'default'
		);
	}

	public static function render_items_box( $post ) {
		wp_nonce_field( 'cv_tv_save_playlist_' . $post->ID, 'cv_tv_playlist_nonce' );

		$items = get_post_meta( $post->ID, '_cv_tv_playlist_items', true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$all_screens = get_posts( array(
			'post_type'      => 'cv_tv_screen',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'suppress_filters' => false,
		) );
		$transitions = array(
			'fade'  => __( 'Dissolvenza', 'casa-volterra-tv-launcher' ),
			'slide' => __( 'Scorrimento', 'casa-volterra-tv-launcher' ),
			'cut'   => __( 'Taglio netto', 'casa-volterra-tv-launcher' ),
		);
		?>
		<p><?php esc_html_e( 'Trascina le righe per riordinare. Clicca "+ Aggiungi schermata" per inserire una nuova voce.', 'casa-volterra-tv-launcher' ); ?></p>

		<table class="widefat" id="cv-tv-playlist-table">
			<thead>
				<tr>
					<th style="width:30px">≡</th>
					<th><?php esc_html_e( 'Schermata', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Durata (sec)', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'Transizione', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:50px"></th>
				</tr>
			</thead>
			<tbody id="cv-tv-playlist-items">
				<?php foreach ( $items as $idx => $item ) :
					$screen_id  = (int) ( $item['screen_id'] ?? 0 );
					$duration   = (int) ( $item['duration'] ?? 10 );
					$transition = $item['transition'] ?? 'fade';
					?>
					<tr class="cv-tv-playlist-row" data-idx="<?php echo esc_attr( (string) $idx ); ?>">
						<td class="cv-tv-drag-handle" style="cursor:grab;font-size:1.3em;color:#999;">≡</td>
						<td>
							<select name="cv_tv_playlist_items[<?php echo esc_attr( (string) $idx ); ?>][screen_id]" class="widefat">
								<option value=""><?php esc_html_e( '— Seleziona schermata —', 'casa-volterra-tv-launcher' ); ?></option>
								<?php foreach ( $all_screens as $s ) : ?>
									<option value="<?php echo esc_attr( (string) $s->ID ); ?>" <?php selected( $screen_id, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" min="3" max="3600" name="cv_tv_playlist_items[<?php echo esc_attr( (string) $idx ); ?>][duration]" value="<?php echo esc_attr( (string) $duration ); ?>" style="width:90px;" /></td>
						<td>
							<select name="cv_tv_playlist_items[<?php echo esc_attr( (string) $idx ); ?>][transition]">
								<?php foreach ( $transitions as $val => $lbl ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $transition, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><button type="button" class="button cv-tv-remove-row">✕</button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:12px;">
			<button type="button" class="button button-primary" id="cv-tv-add-row"><?php esc_html_e( '+ Aggiungi schermata', 'casa-volterra-tv-launcher' ); ?></button>
		</p>

		<!-- Template row (hidden) -->
		<template id="cv-tv-row-template">
			<tr class="cv-tv-playlist-row">
				<td class="cv-tv-drag-handle" style="cursor:grab;font-size:1.3em;color:#999;">≡</td>
				<td>
					<select name="cv_tv_playlist_items[__IDX__][screen_id]" class="widefat">
						<option value=""><?php esc_html_e( '— Seleziona schermata —', 'casa-volterra-tv-launcher' ); ?></option>
						<?php foreach ( $all_screens as $s ) : ?>
							<option value="<?php echo esc_attr( (string) $s->ID ); ?>"><?php echo esc_html( $s->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="number" min="3" max="3600" name="cv_tv_playlist_items[__IDX__][duration]" value="10" style="width:90px;" /></td>
				<td>
					<select name="cv_tv_playlist_items[__IDX__][transition]">
						<?php foreach ( $transitions as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><button type="button" class="button cv-tv-remove-row">✕</button></td>
			</tr>
		</template>
		<?php
	}

	public static function render_settings_box( $post ) {
		$loop     = get_post_meta( $post->ID, '_cv_tv_playlist_loop', true );
		$progress = get_post_meta( $post->ID, '_cv_tv_playlist_progress', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Loop', 'casa-volterra-tv-launcher' ); ?></th>
				<td><label><input type="checkbox" name="cv_tv_playlist_loop" value="1" <?php checked( '1', $loop ); ?> /> <?php esc_html_e( 'Ripeti la playlist in loop continuo', 'casa-volterra-tv-launcher' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Barra avanzamento', 'casa-volterra-tv-launcher' ); ?></th>
				<td><label><input type="checkbox" name="cv_tv_playlist_progress" value="1" <?php checked( '1', $progress ); ?> /> <?php esc_html_e( 'Mostra la barra di avanzamento in basso', 'casa-volterra-tv-launcher' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/* ── Save meta ─────────────────────────────────────────── */

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['cv_tv_playlist_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_playlist_nonce'] ) ), 'cv_tv_save_playlist_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Items
		$raw   = isset( $_POST['cv_tv_playlist_items'] ) && is_array( $_POST['cv_tv_playlist_items'] ) ? $_POST['cv_tv_playlist_items'] : array();
		$items = array();
		$valid_transitions = array( 'fade', 'slide', 'cut' );
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$screen_id = absint( $item['screen_id'] ?? 0 );
			if ( ! $screen_id ) {
				continue;
			}
			$duration   = min( 3600, max( 3, absint( $item['duration'] ?? 10 ) ) );
			$transition = sanitize_key( $item['transition'] ?? 'fade' );
			if ( ! in_array( $transition, $valid_transitions, true ) ) {
				$transition = 'fade';
			}
			$items[] = array( 'screen_id' => $screen_id, 'duration' => $duration, 'transition' => $transition );
		}
		update_post_meta( $post_id, '_cv_tv_playlist_items', $items );

		// Options
		update_post_meta( $post_id, '_cv_tv_playlist_loop',     ! empty( $_POST['cv_tv_playlist_loop'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_cv_tv_playlist_progress', ! empty( $_POST['cv_tv_playlist_progress'] ) ? '1' : '0' );
	}

	/* ── Admin columns ─────────────────────────────────────── */

	public static function columns( $cols ) {
		$cols['cv_tv_screens_count'] = __( 'Schermate', 'casa-volterra-tv-launcher' );
		$cols['cv_tv_duration']      = __( 'Durata totale', 'casa-volterra-tv-launcher' );
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		$items = get_post_meta( $post_id, '_cv_tv_playlist_items', true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		if ( 'cv_tv_screens_count' === $col ) {
			echo esc_html( (string) count( $items ) );
		} elseif ( 'cv_tv_duration' === $col ) {
			$total = array_sum( array_column( $items, 'duration' ) );
			$mins  = intdiv( $total, 60 );
			$secs  = $total % 60;
			echo esc_html( $mins . 'm ' . $secs . 's' );
		}
	}

	/* ── Data helper ────────────────────────────────────────── */

	public static function get_playlist_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::PT !== $post->post_type ) {
			return null;
		}
		$items   = get_post_meta( $post_id, '_cv_tv_playlist_items', true );
		$screens = array();
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				$data = CV_TV_Screens::get_screen_data(
					(int) $item['screen_id'],
					(int) ( $item['duration'] ?? 10 ),
					(string) ( $item['transition'] ?? 'fade' )
				);
				if ( $data ) {
					$screens[] = $data;
				}
			}
		}
		return array(
			'loop'     => '1' === get_post_meta( $post_id, '_cv_tv_playlist_loop', true ),
			'progress' => '1' === get_post_meta( $post_id, '_cv_tv_playlist_progress', true ),
			'screens'  => $screens,
		);
	}
}
