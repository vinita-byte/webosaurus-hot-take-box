<?php
/**
 * Offline pun engine. No AI, no network: it pulls the strongest keywords out of
 * the article and drops them into dinosaur flavoured templates, shuffled so
 * every draft comes out different. It is the fallback when no API key is set.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template based take generator.
 */
class WHTB_Puns {

	/**
	 * Templates. {k} is the keyword, {K} the keyword in title case, {m} the mascot.
	 *
	 * @return array<int,array{0:string,1:string}> template, mood.
	 */
	public static function templates() {
		return array(
			array( '{K}? Roarsome. Tiny arms, big applause.', 'hype' ),
			array( 'Saur baat ki ek baat: {k} matters.', 'smug' ),
			array( 'Jurassic spark alert: {k} just got a lot less extinct.', 'shocked' ),
			array( '{K} explained so well, my tiny arms finally clapped.', 'cackling' ),
			array( 'Prehistoric brain, modern {k}. We evolve together.', 'thinking' ),
			array( 'If {k} were a meteor, this post is the crater. Big impact.', 'shocked' ),
			array( 'Saur loser? Not after this much {k}.', 'smug' ),
			array( 'Highlighter out. Every line on {k} got marked.', 'thinking' ),
			array( 'Fossil fuel for thought: {k}, served fresh.', 'talk' ),
			array( 'Raptor attention span, yet I finished every word on {k}.', 'cackling' ),
			array( '{K}: dino approved, meteor proof.', 'smug' ),
			array( 'Don\'t be a dinosaur about {k}. Says the dinosaur.', 'cackling' ),
			array( '{m} verdict on {k}: chomp worthy.', 'hype' ),
			array( 'Bilkul T rexpected: {k} wins again.', 'smug' ),
			array( 'Sixty five million years and {k} is still the hottest take.', 'shocked' ),
			array( 'My roar has been upgraded to a {k} roar.', 'hype' ),
			array( 'Tyranno saurus? More like tyranno sure, {k} is the move.', 'smug' ),
			array( 'Dig deep, they said. Found {k}. Found treasure.', 'thinking' ),
			array( '{K} so good it gave this fossil a second life.', 'cackling' ),
			array( 'Extinct is a mood. {K} is a lifestyle.', 'idle' ),
		);
	}

	/**
	 * Words that never become the joke.
	 *
	 * @return array<string,bool>
	 */
	public static function stopwords() {
		static $list = null;
		if ( null === $list ) {
			$words = 'about above after again against all also always among and another any are around because been before being below between both but can cannot could did does doing done down during each either else enough even ever every few first for from further get gets getting give given gives going gone got had has have having here hers herself him himself his how however into its itself just keep know last least less like little made make makes making many may maybe more most much must myself need needs never next not now off often once one only onto other others ought our ours ourselves out over own part per perhaps put quite rather really right said same say says see seem seems several shall she should show since some something sometimes still such sure take than that the their theirs them themselves then there these they thing things think this those though through thus till too toward under until upon use used uses using very want was way ways well were what whatever when where whether which while who whole whom whose why will with within without would year years yet you your yours yourself yourselves also lot lots new good great best better big small really things thing people time times day days today tomorrow yesterday here http https www com html read post article blog';
			$list  = array_fill_keys( explode( ' ', $words ), true );
		}
		return $list;
	}

	/**
	 * Top keywords of an article. Title words count triple.
	 *
	 * @param string $title Title.
	 * @param string $text  Plain text body.
	 * @param int    $limit How many.
	 * @return string[]
	 */
	public static function keywords( $title, $text, $limit = 6 ) {
		$stop   = self::stopwords();
		$counts = array();
		$tally  = static function ( $source, $weight ) use ( &$counts, $stop ) {
			$source = function_exists( 'mb_strtolower' ) ? mb_strtolower( $source ) : strtolower( $source );
			preg_match_all( "/[\p{L}][\p{L}']{3,}/u", $source, $m );
			foreach ( $m[0] as $w ) {
				$w = trim( $w, "'" );
				if ( strlen( $w ) < 4 || isset( $stop[ $w ] ) ) {
					continue;
				}
				$counts[ $w ] = ( isset( $counts[ $w ] ) ? $counts[ $w ] : 0 ) + $weight;
			}
		};
		$tally( $title, 3 );
		$tally( $text, 1 );

		// Two word phrases that repeat across title and body ("dark mode") beat
		// single words as punchlines, so they go first.
		$lower   = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		$bigrams = array();
		foreach ( array( array( $title, 3 ), array( $text, 1 ) ) as $pair ) {
			foreach ( preg_split( '/[.!?,;:()\[\]"]+/u', $lower( $pair[0] ) ) as $chunk ) {
				preg_match_all( "/[\p{L}][\p{L}']*/u", $chunk, $m );
				$w     = $m[0];
				$count = count( $w );
				for ( $i = 0; $i < $count - 1; $i++ ) {
					if ( strlen( $w[ $i ] ) > 2 && strlen( $w[ $i + 1 ] ) > 3 && ! isset( $stop[ $w[ $i ] ] ) && ! isset( $stop[ $w[ $i + 1 ] ] ) ) {
						$b             = $w[ $i ] . ' ' . $w[ $i + 1 ];
						$bigrams[ $b ] = ( isset( $bigrams[ $b ] ) ? $bigrams[ $b ] : 0 ) + $pair[1];
					}
				}
			}
		}
		$bigrams = array_filter(
			$bigrams,
			static function ( $n ) {
				return $n >= 4;
			}
		);
		arsort( $bigrams );
		$phrases = array_keys( $bigrams );

		// Words already inside a chosen phrase would repeat the same joke.
		$in_phrase = array();
		foreach ( array_slice( $phrases, 0, 2 ) as $ph ) {
			foreach ( explode( ' ', $ph ) as $pw ) {
				$in_phrase[ $pw ] = true;
			}
		}
		arsort( $counts );
		$words = array();
		foreach ( array_keys( $counts ) as $w ) {
			if ( ! isset( $in_phrase[ $w ] ) ) {
				$words[] = $w;
			}
		}
		$words = array_slice( $words, 0, $limit );
		return array_values( array_unique( array_merge( array_slice( $phrases, 0, 2 ), $words ) ) );
	}

	/**
	 * Generate takes.
	 *
	 * @param string $title  Title.
	 * @param string $text   Plain text.
	 * @param int    $count  How many.
	 * @param string $mascot Mascot name.
	 * @return array<int,array{text:string,mood:string}>
	 */
	public static function generate( $title, $text, $count, $mascot ) {
		$keys = self::keywords( $title, $text );
		if ( ! $keys ) {
			$keys = array( 'this post' );
		}
		$templates = self::templates();
		shuffle( $templates );
		$out = array();
		$n   = count( $keys );
		foreach ( $templates as $i => $tpl ) {
			if ( count( $out ) >= $count ) {
				break;
			}
			// Rotate through the three strongest keywords, strongest first.
			$k     = $keys[ $i % max( 1, min( $n, 3 ) ) ];
			$line  = strtr(
				$tpl[0],
				array(
					'{k}' => $k,
					'{K}' => function_exists( 'mb_convert_case' ) ? mb_convert_case( $k, MB_CASE_TITLE, 'UTF-8' ) : ucwords( $k ),
					'{m}' => $mascot,
				)
			);
			$out[] = array(
				'text' => $line,
				'mood' => $tpl[1],
			);
		}
		return $out;
	}
}
