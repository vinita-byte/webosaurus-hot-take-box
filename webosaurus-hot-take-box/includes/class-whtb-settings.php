<?php
/**
 * Settings: AI provider, keys, voice and display options.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and the settings screen.
 */
class WHTB_Settings {

	const OPTION     = 'whtb_settings';
	const KEY_OPTION = 'whtb_keys';

	/**
	 * Providers and their default models.
	 *
	 * @return array<string,array{label:string,model:string,key_url:string}>
	 */
	public static function providers() {
		return array(
			'anthropic' => array(
				'label'   => 'Anthropic Claude',
				'model'   => 'claude-opus-5-5',
				'key_url' => 'https://console.anthropic.com/settings/keys',
			),
			'openai'    => array(
				'label'   => 'OpenAI',
				'model'   => 'gpt-5-mini',
				'key_url' => 'https://platform.openai.com/api-keys',
			),
			'gemini'    => array(
				'label'   => 'Google Gemini',
				'model'   => 'gemini-2.5-flash',
				'key_url' => 'https://aistudio.google.com/apikey',
			),
			'groq'      => array(
				'label'   => 'Groq (free tier)',
				'model'   => 'llama-3.3-70b-versatile',
				'key_url' => 'https://console.groq.com/keys',
			),
			'offline'   => array(
				'label'   => __( 'Offline pun engine (no API key, no AI)', 'webosaurus-hot-take-box' ),
				'model'   => '',
				'key_url' => '',
			),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'provider'     => 'anthropic',
			'models'       => array(),
			'mascot'       => 'WebSaurus',
			'label'        => 'WebSaurus hot take',
			'voice'        => "Cheeky, warm and a little nerdy, like a topical billboard that makes the whole city grin. Indian English is welcome and a sprinkle of Hinglish is fine. Never mean to real people.",
			'count'        => 5,
			'auto_draft'   => 1,
			'auto_append'  => 1,
			'post_types'   => array( 'post' ),
			'rotate'       => 0,
			'strip_dashes' => 1,
			'accent'       => '#D40016',
		);
	}

	/** Hooks. */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/** Seed defaults on activation. */
	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		if ( false === get_option( self::KEY_OPTION ) ) {
			add_option( self::KEY_OPTION, array(), '', false );
		}
	}

	/**
	 * Read a setting, or all of them.
	 *
	 * @param string|null $key Setting name.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$all = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $all;
		}
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Model for a provider, falling back to its default.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function model( $provider ) {
		$models    = (array) self::get( 'models' );
		$providers = self::providers();
		if ( ! empty( $models[ $provider ] ) ) {
			return $models[ $provider ];
		}
		return isset( $providers[ $provider ] ) ? $providers[ $provider ]['model'] : '';
	}

	/**
	 * API key for a provider. A wp-config constant wins over the stored key,
	 * e.g. define( 'WHTB_ANTHROPIC_KEY', '...' ).
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function key( $provider ) {
		$const = 'WHTB_' . strtoupper( $provider ) . '_KEY';
		if ( defined( $const ) && constant( $const ) ) {
			return (string) constant( $const );
		}
		$keys = (array) get_option( self::KEY_OPTION, array() );
		return isset( $keys[ $provider ] ) ? (string) $keys[ $provider ] : '';
	}

	/**
	 * Whether a key comes from wp-config.
	 *
	 * @param string $provider Provider slug.
	 * @return bool
	 */
	public static function key_is_constant( $provider ) {
		$const = 'WHTB_' . strtoupper( $provider ) . '_KEY';
		return defined( $const ) && constant( $const );
	}

	/**
	 * Post types the box runs on.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array_filter( (array) self::get( 'post_types' ) );
		return $types ? array_values( $types ) : array( 'post' );
	}

	/** Register both options with sanitizers. */
	public static function register() {
		register_setting(
			'whtb',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the settings form. Keys arrive in the same form under whtb_key[]
	 * and are saved into their own non autoloaded option.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$defaults  = self::defaults();
		$providers = self::providers();
		$out       = array();

		$out['provider'] = isset( $input['provider'], $providers[ $input['provider'] ] ) ? $input['provider'] : $defaults['provider'];

		$out['models'] = array();
		if ( isset( $input['models'] ) && is_array( $input['models'] ) ) {
			foreach ( $input['models'] as $p => $m ) {
				if ( isset( $providers[ $p ] ) ) {
					$m = preg_replace( '/[^A-Za-z0-9._:\/-]/', '', (string) $m );
					if ( $m && $m !== $providers[ $p ]['model'] ) {
						$out['models'][ $p ] = $m;
					}
				}
			}
		}

		$out['mascot']       = isset( $input['mascot'] ) && '' !== trim( $input['mascot'] ) ? sanitize_text_field( $input['mascot'] ) : $defaults['mascot'];
		$out['label']        = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : $defaults['label'];
		$out['voice']        = isset( $input['voice'] ) ? sanitize_textarea_field( $input['voice'] ) : $defaults['voice'];
		$out['count']        = isset( $input['count'] ) ? max( 3, min( 8, (int) $input['count'] ) ) : $defaults['count'];
		$out['auto_draft']   = empty( $input['auto_draft'] ) ? 0 : 1;
		$out['auto_append']  = empty( $input['auto_append'] ) ? 0 : 1;
		$out['rotate']       = empty( $input['rotate'] ) ? 0 : 1;
		$out['strip_dashes'] = empty( $input['strip_dashes'] ) ? 0 : 1;
		$accent              = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		$out['accent']       = $accent ? $accent : $defaults['accent'];

		$out['post_types'] = array();
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
		}
		if ( ! $out['post_types'] ) {
			$out['post_types'] = array( 'post' );
		}

		// Keys: blank keeps the saved one, the remove box clears it.
		// The nonce was already checked by options.php before this callback runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$keys    = (array) get_option( self::KEY_OPTION, array() );
		$new     = isset( $_POST['whtb_key'] ) && is_array( $_POST['whtb_key'] ) ? wp_unslash( $_POST['whtb_key'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$removes = isset( $_POST['whtb_key_remove'] ) && is_array( $_POST['whtb_key_remove'] ) ? wp_unslash( $_POST['whtb_key_remove'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable
		foreach ( $providers as $p => $info ) {
			if ( 'offline' === $p ) {
				continue;
			}
			if ( ! empty( $removes[ $p ] ) ) {
				unset( $keys[ $p ] );
			} elseif ( isset( $new[ $p ] ) && '' !== trim( (string) $new[ $p ] ) ) {
				$keys[ $p ] = preg_replace( '/\s+/', '', sanitize_text_field( (string) $new[ $p ] ) );
			}
		}
		update_option( self::KEY_OPTION, $keys, false );

		return $out;
	}

	/** Render the settings screen. */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = self::get();
		$providers = self::providers();
		$name      = self::OPTION;
		?>
		<div class="wrap whtb-settings">
			<h1><?php esc_html_e( 'Hot Take Box settings', 'webosaurus-hot-take-box' ); ?></h1>
			<p class="whtb-lede"><?php esc_html_e( 'AI drafts the takes. A human approves the one that goes live. Nothing shows on your site until you say so.', 'webosaurus-hot-take-box' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'whtb' ); ?>

				<h2><?php esc_html_e( 'The writer', 'webosaurus-hot-take-box' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="whtb-provider"><?php esc_html_e( 'Who drafts the takes', 'webosaurus-hot-take-box' ); ?></label></th>
						<td>
							<select id="whtb-provider" name="<?php echo esc_attr( $name ); ?>[provider]">
								<?php foreach ( $providers as $p => $info ) : ?>
									<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $s['provider'], $p ); ?>><?php echo esc_html( $info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'With no key saved for the chosen provider, the offline pun engine fills in so the desk never sits empty.', 'webosaurus-hot-take-box' ); ?></p>
						</td>
					</tr>
					<?php
					foreach ( $providers as $p => $info ) :
						if ( 'offline' === $p ) {
							continue;
						}
						$saved = self::key( $p );
						?>
						<tr class="whtb-provider-row" data-provider="<?php echo esc_attr( $p ); ?>">
							<th scope="row"><?php echo esc_html( $info['label'] ); ?></th>
							<td>
								<p>
									<span class="whtb-field-label"><?php esc_html_e( 'API key', 'webosaurus-hot-take-box' ); ?></span><br>
									<?php if ( self::key_is_constant( $p ) ) : ?>
										<code><?php echo esc_html( 'WHTB_' . strtoupper( $p ) . '_KEY' ); ?></code> <?php esc_html_e( 'is set in wp-config.php', 'webosaurus-hot-take-box' ); ?>
									<?php else : ?>
										<input type="password" class="regular-text" aria-label="<?php echo esc_attr( $info['label'] . ' API key' ); ?>" autocomplete="new-password" name="whtb_key[<?php echo esc_attr( $p ); ?>]" value="" placeholder="<?php echo $saved ? esc_attr( sprintf( /* translators: %s: last four characters of the key */ __( 'Saved, ends in %s. Leave blank to keep.', 'webosaurus-hot-take-box' ), substr( $saved, -4 ) ) ) : ''; ?>">
										<?php if ( $saved ) : ?>
											<label class="whtb-inline"><input type="checkbox" name="whtb_key_remove[<?php echo esc_attr( $p ); ?>]" value="1"> <?php esc_html_e( 'Remove', 'webosaurus-hot-take-box' ); ?></label>
										<?php endif; ?>
									<?php endif; ?>
								</p>
								<p>
									<label><?php esc_html_e( 'Model', 'webosaurus-hot-take-box' ); ?><br>
									<input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[models][<?php echo esc_attr( $p ); ?>]" value="<?php echo esc_attr( self::model( $p ) ); ?>" placeholder="<?php echo esc_attr( $info['model'] ); ?>"></label>
								</p>
								<p class="description"><a href="<?php echo esc_url( $info['key_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get a key', 'webosaurus-hot-take-box' ); ?></a></p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="whtb-voice"><?php esc_html_e( 'House voice', 'webosaurus-hot-take-box' ); ?></label></th>
						<td>
							<textarea id="whtb-voice" class="large-text" rows="4" name="<?php echo esc_attr( $name ); ?>[voice]"><?php echo esc_textarea( $s['voice'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Sent to the AI with every article. Describe the tone, the jokes you love and the lines you never cross.', 'webosaurus-hot-take-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="whtb-count"><?php esc_html_e( 'Takes per draft', 'webosaurus-hot-take-box' ); ?></label></th>
						<td><input id="whtb-count" type="number" min="3" max="8" name="<?php echo esc_attr( $name ); ?>[count]" value="<?php echo esc_attr( $s['count'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Drafting', 'webosaurus-hot-take-box' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_draft]" value="1" <?php checked( $s['auto_draft'] ); ?>> <?php esc_html_e( 'Draft takes in the background when a post is published, then wait on the desk for approval', 'webosaurus-hot-take-box' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[strip_dashes]" value="1" <?php checked( $s['strip_dashes'] ); ?>> <?php esc_html_e( 'Strip em dashes, en dashes and hyphenated words from drafts', 'webosaurus-hot-take-box' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'The box', 'webosaurus-hot-take-box' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="whtb-mascot"><?php esc_html_e( 'Mascot name', 'webosaurus-hot-take-box' ); ?></label></th>
						<td><input id="whtb-mascot" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[mascot]" value="<?php echo esc_attr( $s['mascot'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="whtb-label"><?php esc_html_e( 'Box label', 'webosaurus-hot-take-box' ); ?></label></th>
						<td><input id="whtb-label" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $s['label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="whtb-accent"><?php esc_html_e( 'Accent colour', 'webosaurus-hot-take-box' ); ?></label></th>
						<td><input id="whtb-accent" type="color" name="<?php echo esc_attr( $name ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'webosaurus-hot-take-box' ); ?></th>
						<td>
							<?php
							foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) :
								if ( 'attachment' === $pt->name ) {
									continue;
								}
								?>
								<label class="whtb-inline"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, self::post_types(), true ) ); ?>> <?php echo esc_html( $pt->labels->name ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Display', 'webosaurus-hot-take-box' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_append]" value="1" <?php checked( $s['auto_append'] ); ?>> <?php esc_html_e( 'Add the box to the end of every post automatically', 'webosaurus-hot-take-box' ); ?></label>
							<p class="description"><?php echo wp_kses_post( __( 'Turn this off to place it yourself with the <code>[webosaurus_hot_take]</code> shortcode.', 'webosaurus-hot-take-box' ) ); ?></p>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[rotate]" value="1" <?php checked( $s['rotate'] ); ?>> <?php esc_html_e( 'Rotate: when a post has several approved takes, show a random one on each visit', 'webosaurus-hot-take-box' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		( function () {
			var sel = document.getElementById( 'whtb-provider' );
			if ( ! sel ) { return; }
			function sync() {
				document.querySelectorAll( '.whtb-provider-row' ).forEach( function ( row ) {
					row.style.display = row.getAttribute( 'data-provider' ) === sel.value ? '' : 'none';
				} );
			}
			sel.addEventListener( 'change', sync );
			sync();
		}() );
		</script>
		<?php
	}
}
