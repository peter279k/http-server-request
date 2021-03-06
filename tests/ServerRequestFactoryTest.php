<?php

namespace Sunrise\Http\ServerRequest\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Sunrise\Http\ServerRequest\ServerRequestFactory;

class ServerRequestFactoryTest extends TestCase
{
	private $tmpfiles = [];

	public function testConstructor()
	{
		$factory = new ServerRequestFactory();

		$this->assertInstanceOf(ServerRequestFactoryInterface::class, $factory);
	}

	public function testCreateServerRequest()
	{
		$method = 'GET';
		$uri = 'http://localhost:3000/';
		$server = $_SERVER;

		$request = (new ServerRequestFactory)->createServerRequest($method, $uri, $server);

		$this->assertInstanceOf(ServerRequestInterface::class, $request);
		$this->assertEquals($method, $request->getMethod());
		$this->assertEquals($uri, (string) $request->getUri());
		$this->assertEquals($server, $request->getServerParams());

		// default body of the request...
		$this->assertInstanceOf(StreamInterface::class, $request->getBody());
		$this->assertTrue($request->getBody()->isSeekable());
		$this->assertTrue($request->getBody()->isWritable());
		$this->assertTrue($request->getBody()->isReadable());
		$this->assertEquals('php://temp', $request->getBody()->getMetadata('uri'));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCreateServerRequestFromGlobals()
	{
		$file = ['tmp_name' => $this->tmpfile(), 'size' => 0, 'error' => \UPLOAD_ERR_OK, 'name' => '', 'type' => ''];

		$_SERVER = ['foo'  => 'bar'];
		$_GET    = ['bar'  => 'baz'];
		$_POST   = ['baz'  => 'qux'];
		$_COOKIE = ['qux'  => 'quux'];
		$_FILES  = ['quux' => $file];

		$request = ServerRequestFactory::fromGlobals();
		$this->assertInstanceOf(ServerRequestInterface::class, $request);

		$this->assertEquals($_SERVER, $request->getServerParams());
		$this->assertEquals($_GET, $request->getQueryParams());
		$this->assertEquals($_POST, $request->getParsedBody());
		$this->assertEquals($_COOKIE, $request->getCookieParams());
		$this->assertEquals($_FILES['quux']['tmp_name'], $request->getUploadedFiles()['quux']->getStream()->getMetadata('uri'));
	}

	public function testCreateServerRequestFromGlobalsWithServer()
	{
		$server = ['foo' => 'bar'];
		$request = ServerRequestFactory::fromGlobals($server, [], [], [], []);
		$this->assertEquals($server, $request->getServerParams());
	}

	public function testCreateServerRequestFromGlobalsWithQuery()
	{
		$query = ['foo' => 'bar'];
		$request = ServerRequestFactory::fromGlobals([], $query, [], [], []);
		$this->assertEquals($query, $request->getQueryParams());
	}

	public function testCreateServerRequestFromGlobalsWithBody()
	{
		$body = ['foo' => 'bar'];
		$request = ServerRequestFactory::fromGlobals([], [], $body, [], []);
		$this->assertEquals($body, $request->getParsedBody());
	}

	public function testCreateServerRequestFromGlobalsWithCookies()
	{
		$cookies = ['foo' => 'bar'];
		$request = ServerRequestFactory::fromGlobals([], [], [], $cookies, []);
		$this->assertEquals($cookies, $request->getCookieParams());
	}

	public function testCreateServerRequestFromGlobalsWithFiles()
	{
		$files['foo']['tmp_name'] = $this->tmpfile();
		$files['foo']['size'] = 0;
		$files['foo']['error'] = \UPLOAD_ERR_OK;
		$files['foo']['name'] = 'foo.txt';
		$files['foo']['type'] = 'text/plain';

		$files['bar']['tmp_name'][0] = $this->tmpfile();
		$files['bar']['size'][0] = 0;
		$files['bar']['error'][0] = \UPLOAD_ERR_OK;
		$files['bar']['name'][0] = 'bar.txt';
		$files['bar']['type'][0] = 'text/plain';

		$request = ServerRequestFactory::fromGlobals([], [], [], [], $files);
		$uploadedFiles = $request->getUploadedFiles();

		$this->assertEquals($files['foo']['tmp_name'], $uploadedFiles['foo']->getStream()->getMetadata('uri'));
		$this->assertEquals($files['foo']['size'], $uploadedFiles['foo']->getSize());
		$this->assertEquals($files['foo']['error'], $uploadedFiles['foo']->getError());
		$this->assertEquals($files['foo']['name'], $uploadedFiles['foo']->getClientFilename());
		$this->assertEquals($files['foo']['type'], $uploadedFiles['foo']->getClientMediaType());

		$this->assertEquals($files['bar']['tmp_name'][0], $uploadedFiles['bar'][0]->getStream()->getMetadata('uri'));
		$this->assertEquals($files['bar']['size'][0], $uploadedFiles['bar'][0]->getSize());
		$this->assertEquals($files['bar']['error'][0], $uploadedFiles['bar'][0]->getError());
		$this->assertEquals($files['bar']['name'][0], $uploadedFiles['bar'][0]->getClientFilename());
		$this->assertEquals($files['bar']['type'][0], $uploadedFiles['bar'][0]->getClientMediaType());
	}

	public function testHeadersFromGlobals()
	{
		$request = ServerRequestFactory::fromGlobals(['FOO' => 'bar']);
		$this->assertEquals([], $request->getHeader('foo'));

		$request = ServerRequestFactory::fromGlobals(['HTTP_FOO' => 'bar']);
		$this->assertEquals(['bar'], $request->getHeader('foo'));
	}

	public function testProtocolVersionFromGlobals()
	{
		$request = ServerRequestFactory::fromGlobals(['SERVER_PROTOCOL' => 'HTTP/2.0']);
		$this->assertEquals('2.0', $request->getProtocolVersion());

		$request = ServerRequestFactory::fromGlobals(['SERVER_PROTOCOL' => 'HTTP/3']);
		$this->assertEquals('3', $request->getProtocolVersion());
	}

	public function testMethodFromGlobals()
	{
		$request = ServerRequestFactory::fromGlobals(['REQUEST_METHOD' => 'POST']);
		$this->assertEquals('POST', $request->getMethod());

		$request = ServerRequestFactory::fromGlobals(['REQUEST_METHOD' => 'UNKNOWN']);
		$this->assertEquals('UNKNOWN', $request->getMethod());
	}

	public function testUriFromGlobals()
	{
		$request = ServerRequestFactory::fromGlobals([]);
		$this->assertEquals('http://localhost/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['HTTPS' => 'off']);
		$this->assertEquals('http://localhost/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['HTTPS' => 'on']);
		$this->assertEquals('https://localhost/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['HTTP_HOST' => 'example.com']);
		$this->assertEquals('http://example.com/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['HTTP_HOST' => 'example.com:3000']);
		$this->assertEquals('http://example.com:3000/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['SERVER_NAME' => 'example.com']);
		$this->assertEquals('http://example.com/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['SERVER_NAME' => 'example.com', 'SERVER_PORT' => 3000]);
		$this->assertEquals('http://example.com:3000/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['SERVER_PORT' => 3000]);
		$this->assertEquals('http://localhost/', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/path']);
		$this->assertEquals('http://localhost/path', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/path?query']);
		$this->assertEquals('http://localhost/path?query', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['PHP_SELF' => '/path']);
		$this->assertEquals('http://localhost/path', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['PHP_SELF' => '/path', 'QUERY_STRING' => 'query']);
		$this->assertEquals('http://localhost/path?query', (string) $request->getUri());

		$request = ServerRequestFactory::fromGlobals(['QUERY_STRING' => 'query']);
		$this->assertEquals('http://localhost/', (string) $request->getUri());
	}

	public function tearDown()
	{
		$tmpfiles = $this->tmpfiles;

		$this->tmpfiles = [];

		foreach ($tmpfiles as $tmpfile)
		{
			@ \unlink($tmpfile);
		}
	}

	private function tmpfile() : string
	{
		$folder = \sys_get_temp_dir();

		$tmpfile = \tempnam($folder, 'sunrise');

		$this->tmpfiles[] = $tmpfile;

		return $tmpfile;
	}
}
