<?php namespace Illuminate\Filesystem;

use Aws\S3\S3Client;
use OpenCloud\Rackspace;
use Illuminate\Support\Manager;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\AwsS3 as S3Adapter;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Adapter\Rackspace as RackspaceAdapter;
use Illuminate\Contracts\Filesystem\Factory as FactoryContract;

class FilesystemManager implements FactoryContract {

	/**
	 * The application instance.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * The array of resolved filesystem drivers.
	 *
	 * @var array
	 */
	protected $disks = [];

	/**
	 * Create a new queue manager instance.
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Get an OAuth provider implementation.
	 *
	 * @param  string  $name
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	public function disk($name = null)
	{
		$name = $name ?: $this->getDefaultDriver();

		return $this->disks[$name] = $this->get($name);
	}

	/**
	 * Attempt to get the disk from the local cache.
	 *
	 * @param  string  $name
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	protected function get($name)
	{
		return isset($this->disks[$name]) ? $this->disks[$name] : $this->resolve($name);
	}

	/**
	 * Resolve the given disk.
	 *
	 * @param  string  $name
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	protected function resolve($name)
	{
		$config = $this->getConfig($name);

		return $this->{"create".ucfirst($config['driver'])."Driver"}($config);
	}

	/**
	 * Create an instance of the given driver.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	public function createLocalDriver(array $config)
	{
		return $this->decorate(new Flysystem(new LocalAdapter($config['root'])));
	}

	/**
	 * Create an instance of the given driver.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Filesystem\Cloud
	 */
	public function createS3Driver(array $config)
	{
		$client = S3Client::factory([
			'key' => $config['key'], 'secret' => $config['secret'],
		]);

		return $this->decorate(
			new Flysystem(new S3Adapter($client, $config['bucket']))
		);
	}

	/**
	 * Create an instance of the given driver.
	 *
	 * @param  array  $config
	 * @return \Illuminate\Contracts\Filesystem\Cloud
	 */
	public function createRackspaceDriver(array $config)
	{
		$client = new Rackspace($config['endpoint'], [
			'username' => $config['username'], 'apiKey' => $config['key'],
		]);

		return $this->decorate(new Flysystem(
			new RackspaceAdapter($this->getRackspaceContainer($client, $config))
		));
	}

	/**
	 * Get the Rackspace Cloud Files container.
	 *
	 * @param  Rackspace  $client
	 * @param  array  $config
	 * @return \OpenCloud\ObjectStore\Resource\Container
	 */
	protected function getRackspaceContainer(Rackspace $client, array $config)
	{
		$store = $client->objectStoreService('cloudFiles', $config['region']);

		return $store->getContainer($config['container']);
	}

	/**
	 * Decorate the filesystem implementation.
	 *
	 * @param  \League\Flysystem\FilesystemInterface  $filesystem
	 * @return \Illuminate\Contracts\Filesystem\Filesystem
	 */
	protected function decorate(FilesystemInterface $filesystem)
	{
		return new FilesystemDecorator($filesystem);
	}

	/**
	 * Get the filesystem connection configuration.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function getConfig($name)
	{
		return $this->app['config']["filesystems.disks.{$name}"];
	}

	/**
	 * Get the default driver name.
	 *
	 * @return string
	 */
	public function getDefaultDriver()
	{
		return $this->app['config']['filesystems.default'];
	}

}
