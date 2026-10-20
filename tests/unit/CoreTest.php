<?php
/**
 * Core value objects, settings, token merge, package helpers.
 *
 * @package ForWP\Social\Tests
 */

namespace ForWP\Social\Tests;

use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Core\Media_Package;
use ForWP\Social\Core\Publish_Result;
use ForWP\Social\Core\Settings;
use ForWP\Social\Modules\Posting\Package_Reader;
use ForWP\Social\Modules\Posting\Post_Reader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ForWP\Social\Core\Media_Package
 * @covers \ForWP\Social\Core\Publish_Result
 * @covers \ForWP\Social\Core\Settings
 * @covers \ForWP\Social\Auth\OAuth_Controller::merge_tokens
 * @covers \ForWP\Social\Modules\Posting\Package_Reader
 */
class CoreTest extends TestCase {

	protected function setUp(): void {
		forwp_social_tests_reset();
	}

	public function test_language_from_srt_name(): void {
		$this->assertSame( 'en', Media_Package::language_from_file( '4wp-faq-overview.en.srt' ) );
		$this->assertSame( 'pt-BR', Media_Package::language_from_file( 'clip.pt_BR.srt' ) );
		$this->assertSame( 'uk', Media_Package::language_from_file( 'clip.srt', 'uk' ) );
	}

	public function test_media_package_ignores_unknown_fields(): void {
		$package = new Media_Package( array( 'title' => 'A', 'bogus' => 1 ) );
		$this->assertArrayNotHasKey( 'bogus', $package->to_array() );
		$this->assertSame( 'B', $package->with( array( 'title' => 'B' ) )->title() );
		$this->assertFalse( $package->has_video() );
	}

	public function test_publish_result_states(): void {
		$this->assertTrue( Publish_Result::published( '1', 'u' )->is_published() );
		$this->assertSame( 'ref', Publish_Result::processing( 'ref' )->reference() );
		$this->assertFalse( Publish_Result::failed( 'x', false )->is_retryable() );
	}

	public function test_settings_defaults_and_save(): void {
		$this->assertTrue( Settings::is_module_enabled( 'posting' ) );
		$this->assertFalse( Settings::is_module_enabled( 'share' ) );

		Settings::save(
			array(
				'modules'         => array( 'share' => '1' ),
				'delay_minutes'   => '99999',
				'default_privacy' => 'everyone',
			)
		);
		$this->assertTrue( Settings::is_module_enabled( 'share' ) );
		$this->assertFalse( Settings::is_module_enabled( 'posting' ) );
		$this->assertSame( 10080, Settings::get( 'delay_minutes' ) );
		$this->assertSame( 'public', Settings::get( 'default_privacy' ) );
	}

	public function test_video_settings_keep_valid_slugs(): void {
		$this->assertTrue( Settings::is_module_enabled( 'video' ) );
		$this->assertSame( 'video', Settings::get( 'video' )['post_type'] );

		Settings::save(
			array(
				'video' => array(
					'post_type' => 'clip',
					'taxonomy'  => '',
					'slug'      => 'Watch Now',
				),
			)
		);
		$video = Settings::get( 'video' );
		$this->assertSame( 'clip', $video['post_type'] );
		$this->assertSame( 'video-series', $video['taxonomy'] );
		$this->assertSame( 'watch-now', $video['slug'] );
		$this->assertFalse( $video['json_ld'] );
	}

	public function test_refresh_does_not_drop_refresh_token(): void {
		$merged = OAuth_Controller::merge_tokens(
			array( 'access_token' => 'old', 'refresh_token' => 'keep', 'account' => 'Chan' ),
			array( 'access_token' => 'new', 'refresh_token' => '', 'expires_at' => 5 )
		);
		$this->assertSame( 'new', $merged['access_token'] );
		$this->assertSame( 'keep', $merged['refresh_token'] );
		$this->assertSame( 'Chan', $merged['account'] );
	}

	public function test_credentials_are_encrypted_and_secret_kept(): void {
		Credentials_Store::save( 'youtube.com', 'id-1', 'secret-1' );
		$this->assertStringNotContainsString( 'secret-1', (string) get_option( Credentials_Store::OPTION_KEY ) );

		Credentials_Store::save( 'youtube.com', 'id-2', '' );
		$this->assertSame( array( 'client_id' => 'id-2', 'client_secret' => 'secret-1' ), Credentials_Store::get( 'youtube.com' ) );
		$this->assertFalse( Credentials_Store::has( 'tiktok.com' ) );
	}

	public function test_api_platforms_and_delay(): void {
		$package = array(
			'platforms' => array(
				'youtube.com' => array( 'mode' => 'api' ),
				'tiktok.com'  => array( 'mode' => 'api' ),
				'x.com'       => array( 'mode' => 'manual' ),
			),
		);
		$this->assertSame( array( 'youtube.com', 'tiktok.com' ), Package_Reader::api_platforms( $package ) );

		update_option( Settings::OPTION_KEY, array( 'delay_minutes' => 15 ) );
		$this->assertSame( 900, Package_Reader::delay_seconds( $package ) );
		$this->assertSame( 2 * 3600 + 300, Package_Reader::delay_seconds( $package + array( 'republish' => array( 'delay_hours' => 2, 'delay_minutes' => 5 ) ) ) );
	}

	public function test_post_reader_sanitize(): void {
		$clean = Post_Reader::sanitize( array( 'video_id' => '12abc', 'privacy' => 'everyone', 'title' => " <b>Hi</b>\n" ) );
		$this->assertSame( 12, $clean['video_id'] );
		$this->assertSame( '', $clean['privacy'] );
		$this->assertSame( 'Hi', $clean['title'] );
	}
}
