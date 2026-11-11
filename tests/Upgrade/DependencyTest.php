<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Upgrade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMCZ\Tests\Support\TemporaryFilesTrait;
use WMCZ\Upgrade\Dependency;
use WMCZ\Upgrade\PrepareException;

#[CoversClass( Dependency::class )]
final class DependencyTest extends TestCase {
	use TemporaryFilesTrait;

	/**
	 * @return array<string,Dependency>
	 */
	private function readList( string $content ): array {
		$file = $this->getTemporaryDirectory() . '/dependencies.txt';
		$this->writeFile( $file, $content );
		return Dependency::readList( $file );
	}

	public function testReadList(): void {
		$list = $this->readList( "# Comment\nskin  Vector\n\nextension Cite   # trailing comment\n\textension\tUserMerge\tmaster\nskin Cite\n" );

		$this->assertSame( [ 'skin Vector', 'extension Cite', 'extension UserMerge', 'skin Cite' ], array_keys( $list ) );
		$this->assertSame( [ null, null, 'master', null ], array_column( $list, 'branch' ) );
		$this->assertSame( 'extensions/UserMerge', $list['extension UserMerge']->getPath() );
		$this->assertSame( 'skins/Vector', $list['skin Vector']->getPath() );
		$this->assertSame( 'skin Vector', (string)$list['skin Vector'] );
	}

	public static function provideInvalid(): iterable {
		yield 'missing name' => [ "extension\n", ':1: expected "<type> <name> [<branch>]"' ];
		yield 'too many columns' => [ "extension Cite master extra\n", ':1: expected "<type> <name> [<branch>]"' ];
		yield 'unknown type' => [ "gadget Foo\n", ':1: unknown type "gadget"' ];
		yield 'path traversal in name' => [ "extension ../../etc\n", ':1: invalid name "../../etc"' ];
		yield 'invalid branch' => [ "extension Cite REL1_46;rm\n", ':1: invalid branch "REL1_46;rm"' ];
		yield 'duplicate' => [ "extension Cite\n\nextension Cite master\n", ':3: extension Cite is listed more than once' ];
		yield 'empty' => [ "# nothing here\n\n", 'does not list any extensions or skins' ];
	}

	#[DataProvider( 'provideInvalid' )]
	public function testInvalid( string $content, string $expectedMessage ): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( $expectedMessage );
		$this->readList( $content );
	}

	public function testMissingFile(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'Unable to read' );
		Dependency::readList( $this->getTemporaryDirectory() . '/missing.txt' );
	}
}
