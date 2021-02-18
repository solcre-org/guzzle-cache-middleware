<?php

namespace Kevinrob\GuzzleCache\Storage;

use Kevinrob\GuzzleCache\CacheEntry;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;

class FlysystemStorage implements CacheStorageInterface
{

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(FilesystemAdapter $adapter)
    {
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * @inheritdoc
     */
    public function fetch($key)
    {

        if ($this->filesystem->fileExists($key)) {
            // The file exist, read it!
            $data = @unserialize(
                $this->filesystem->read($key)
            );

            if ($data instanceof CacheEntry) {
                return $data;
            }
        }
        return;
    }

    /**
     * @inheritdoc
     */
    public function save($key, CacheEntry $data)
    {
        return $this->filesystem->write($key, serialize($data));
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->filesystem->delete($key);
        } catch (FileNotFoundException $ex) {
            return true;
        }
    }
}
