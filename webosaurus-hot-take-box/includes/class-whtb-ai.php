<?php
/**
 * AI drafting. One prompt, four providers, plus the offline fallback.
 *
 * Every call picks a random set of comedy angles and a random seed word, so
 * pressing Draft again gives a fresh batch instead of the same five lines.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider calls.
 */
class WHTB_AI {

	/** Characters of article text sent to the model. Plenty for a whole post. */
	const MAX_CHARS = 24000;

	/**
	 * Comedy angles. A few are picked at random per draft.
	 *
	 * @return string[]
	 */
	public static function angles() {
		return array(
			'a pun on the single most important word in the article',
			'a play on a famous Bollywood dialogue or song title',
			'a cricket commentary twist',
			'a dinosaur, fossil or meteor pun (the mascot is a T rex)',
			'a tech or internet slang twist',
			'a desi household or mummy ji observation',
			'a food pun, like a street food stall menu board',
			'a mock serious newspaper headline',
			'a rhyme that lands on the article topic',
			'a double meaning that flips the headline',
			'a Hinglish wordplay that still reads clearly to English readers',
			'an understated deadpan one liner',
		);
	}

	/**
	 * Plain text of a post, shortcodes and blocks stripped.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function article_text( $post ) {
		$content = strip_shortcodes( $post->post_content );
		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$content = excerpt_remove_blocks( $content );
		}
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = trim( preg_replace( '/\s+/u', ' ', $content ) );
		if ( function_exists( 'mb_substr' ) ) {
			$content = mb_substr( $content, 0, self::MAX_CHARS );
		}
		return $content;
	}

	/**
	 * Copy of the post carrying unsaved editor text, so drafts match what the author sees.
	 *
	 * @param WP_Post              $post     Post.
	 * @param array<string,string> $override title and content.
	 * @return WP_Post
	 */
	public static function with_override( $post, $override ) {
		if ( empty( $override ) ) {
			return $post;
		}
		$post = clone $post;
		if ( isset( $override['title'] ) && '' !== trim( $override['title'] ) ) {
			$post->post_title = sanitize_text_field( $override['title'] );
		}
		if ( isset( $override['content'] ) && '' !== trim( $override['content'] ) ) {
			$post->post_content = wp_kses_post( $override['content'] );
		}
		return $post;
	}

	/**
	 * Build the system and user prompts.
	 *
	 * @param WP_Post $post  Post.
	 * @param int     $count Takes wanted.
	 * @return array{system:string,user:string}
	 */
	public static function prompts( $post, $count ) {
		$s      = WHTB_Settings::get();
		$angles = self::angles();
		shuffle( $angles );
		$angles = array_slice( $angles, 0, min( count( $angles ), $count ) );
		$moods  = implode( ', ', array_keys( whtb_moods() ) );

		$system = sprintf(
			"You are %1\$s, a red T rex mascot in nerd glasses and a hoodie who closes every blog post with a one line hot take, like the topical billboards Indian brands run the morning after big news.\n\nHouse voice: %2\$s\n\nRules for every take:\n- One line, at most 16 words.\n- Built on a pun or wordplay about the core idea of the whole article, not a side detail.\n- Clever beats loud. Warm, never mean, never punching down. No politics, religion or tragedy jokes.\n- No hashtags, no emojis, no quotation marks around the line.\n- Never use em dashes, en dashes or hyphenated words. Use commas, colons or full stops.\n- Every take uses a different angle and a different punchline.\n- Give each take one mood from this list that matches how %1\$s says it: %3\$s.\n\nReply with JSON only, no prose and no code fences, in exactly this shape:\n{\"takes\":[{\"text\":\"...\",\"mood\":\"smug\"}]}",
			$s['mascot'],
			$s['voice'],
			$moods
		);

		$user = sprintf(
			"Write %1\$d hot takes for this article. Use these angles, one per take, in any order: %2\$s. Random seed for variety: %3\$s.\n\n<article>\nTitle: %4\$s\n\n%5\$s\n</article>",
			$count,
			implode( '; ', $angles ),
			wp_generate_password( 6, false, false ),
			html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			self::article_text( $post )
		);

		return array(
			'system' => $system,
			'user'   => $user,
		);
	}

	/**
	 * Draft takes for a post.
	 *
	 * @param int                $post_id  Post ID.
	 * @param array<string,string> $override Unsaved title and content from the editor.
	 * @return array{takes:array,engine:string,note?:string}|WP_Error
	 */
	public static function generate( $post_id, $override = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'whtb_no_post', __( 'Post not found.', 'webosaurus-hot-take-box' ), array( 'status' => 404 ) );
		}
		$post = self::with_override( $post, $override );
		if ( '' === self::article_text( $post ) && '' === trim( $post->post_title ) ) {
			return new WP_Error( 'whtb_blank', __( 'Write the post first. Even a T rex cannot riff on a blank page.', 'webosaurus-hot-take-box' ) );
		}
		$s        = WHTB_Settings::get();
		$count    = (int) $s['count'];
		$provider = $s['provider'];
		$key      = 'offline' === $provider ? '' : WHTB_Settings::key( $provider );

		/**
		 * Short circuit drafting, for tests or a custom provider.
		 * Return an array of takes (each with text and mood) to skip the built in providers.
		 *
		 * @param array|null $takes    Null to continue.
		 * @param WP_Post    $post     Post.
		 * @param string     $provider Provider slug.
		 */
		$custom = apply_filters( 'whtb_pre_generate', null, $post, $provider );
		if ( is_array( $custom ) ) {
			return array(
				'takes'  => $custom,
				'engine' => 'custom',
			);
		}

		if ( 'offline' === $provider || '' === $key ) {
			return array(
				'takes'  => WHTB_Puns::generate( get_the_title( $post ), self::article_text( $post ), $count, $s['mascot'] ),
				'engine' => 'offline',
				'note'   => 'offline' === $provider ? '' : __( 'No API key saved, so the offline pun engine wrote these. Add a key in Settings for sharper, article aware takes.', 'webosaurus-hot-take-box' ),
			);
		}

		$prompts = self::prompts( $post, $count );
		$model   = WHTB_Settings::model( $provider );
		switch ( $provider ) {
			case 'anthropic':
				$raw = self::call_anthropic( $key, $model, $prompts );
				break;
			case 'gemini':
				$raw = self::call_gemini( $key, $model, $prompts );
				break;
			case 'groq':
				$raw = self::call_openai_compatible( 'https://api.groq.com/openai/v1/chat/completions', $key, $model, $prompts, true );
				break;
			default:
				$raw = self::call_openai_compatible( 'https://api.openai.com/v1/chat/completions', $key, $model, $prompts, false );
		}
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$takes = self::parse( $raw );
		if ( ! $takes ) {
			return new WP_Error( 'whtb_parse', __( 'The AI replied, but not with takes we could read. Try again.', 'webosaurus-hot-take-box' ) );
		}
		return array(
			'takes'  => $takes,
			'engine' => $provider . ':' . $model,
		);
	}

	/**
	 * Pull the takes list out of a model reply, tolerating code fences or chatter.
	 *
	 * @param string $raw Reply text.
	 * @return array<int,array{text:string,mood:string}>
	 */
	public static function parse( $raw ) {
		$raw  = trim( (string) $raw );
		$raw  = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $raw );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) && preg_match( '/\{.*\}|\[.*\]/s', $raw, $m ) ) {
			$data = json_decode( $m[0], true );
		}
		if ( ! is_array( $data ) ) {
			return array();
		}
		$list = isset( $data['takes'] ) ? $data['takes'] : $data;
		$out  = array();
		foreach ( (array) $list as $item ) {
			if ( is_string( $item ) ) {
				$out[] = array(
					'text' => $item,
					'mood' => 'smug',
				);
			} elseif ( is_array( $item ) && ! empty( $item['text'] ) ) {
				$out[] = array(
					'text' => (string) $item['text'],
					'mood' => isset( $item['mood'] ) ? (string) $item['mood'] : 'smug',
				);
			}
		}
		return $out;
	}

	/**
	 * POST JSON and decode the reply.
	 *
	 * @param string               $url     URL.
	 * @param array<string,string> $headers Headers.
	 * @param array<string,mixed>  $body    Body.
	 * @return array<string,mixed>|WP_Error
	 */
	protected static function post_json( $url, $headers, $body ) {
		$res = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = '';
			if ( is_array( $data ) && isset( $data['error'] ) ) {
				$msg = is_array( $data['error'] ) ? ( isset( $data['error']['message'] ) ? $data['error']['message'] : '' ) : (string) $data['error'];
			}
			/* translators: 1: HTTP status code, 2: provider error message */
			return new WP_Error( 'whtb_http', sprintf( __( 'The AI provider said %1$d. %2$s', 'webosaurus-hot-take-box' ), $code, sanitize_text_field( $msg ) ) );
		}
		return is_array( $data ) ? $data : new WP_Error( 'whtb_json', __( 'The AI provider sent back something that was not JSON.', 'webosaurus-hot-take-box' ) );
	}

	/**
	 * Anthropic Messages API.
	 *
	 * @param string                              $key     Key.
	 * @param string                              $model   Model.
	 * @param array{system:string,user:string}    $prompts Prompts.
	 * @return string|WP_Error
	 */
	protected static function call_anthropic( $key, $model, $prompts ) {
		$headers = array(
			'x-api-key'         => $key,
			'anthropic-version' => '2026-10-21',
		);
		$body    = array(
			'model'      => $model,
			'max_tokens' => 4000,
			'system'     => $prompts['system'],
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompts['user'],
				),
			),
		);
		// Current Claude models take an effort setting. One liners need little thinking.
		if ( preg_match( '/^claude-(opus|sonnet|haiku|fable)-5/', $model ) ) {
			$body['output_config'] = array( 'effort' => 'low' );
		}
		// If a safety classifier declines, let the API retry on a suitable model.
		if ( preg_match( '/^claude-(fable-5-[12]|opus-5|sonnet-5-5)/', $model ) ) {
			$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
			$body['fallbacks']         = 'default';
		}
		$data = self::post_json( 'https://api.anthropic.com/v1/messages', $headers, $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'] ) {
			return new WP_Error( 'whtb_refusal', __( 'Claude declined this article. Write a take by hand or try another provider.', 'webosaurus-hot-take-box' ) );
		}
		$text = '';
		foreach ( isset( $data['content'] ) ? (array) $data['content'] : array() as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}
		return $text;
	}

	/**
	 * OpenAI style chat completions (OpenAI and Groq).
	 *
	 * @param string                           $url         Endpoint.
	 * @param string                           $key         Key.
	 * @param string                           $model       Model.
	 * @param array{system:string,user:string} $prompts     Prompts.
	 * @param bool                             $temperature Whether to send a temperature.
	 * @return string|WP_Error
	 */
	protected static function call_openai_compatible( $url, $key, $model, $prompts, $temperature ) {
		$body = array(
			'model'           => $model,
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $prompts['system'],
				),
				array(
					'role'    => 'user',
					'content' => $prompts['user'],
				),
			),
			'response_format' => array( 'type' => 'json_object' ),
		);
		if ( $temperature ) {
			$body['temperature'] = 1.0;
		}
		$data = self::post_json( $url, array( 'Authorization' => 'Bearer ' . $key ), $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['choices'][0]['message']['content'] ) ? (string) $data['choices'][0]['message']['content'] : '';
	}

	/**
	 * Google Gemini generateContent.
	 *
	 * @param string                           $key     Key.
	 * @param string                           $model   Model.
	 * @param array{system:string,user:string} $prompts Prompts.
	 * @return string|WP_Error
	 */
	protected static function call_gemini( $key, $model, $prompts ) {
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
		$body = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $prompts['system'] ) ) ),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompts['user'] ) ),
				),
			),
			'generationConfig'  => array(
				'temperature'      => 1.1,
				'responseMimeType' => 'application/json',
			),
		);
		$data = self::post_json( $url, array( 'x-goog-api-key' => $key ), $body );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = '';
		foreach ( isset( $data['candidates'][0]['content']['parts'] ) ? (array) $data['candidates'][0]['content']['parts'] : array() as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		return $text;
	}
}
