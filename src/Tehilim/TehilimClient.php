<?php

declare(strict_types=1);

namespace App\Tehilim;

use PDO;
use Polidog\Tehilim\Client\BaseClient;
use Polidog\Tehilim\Client\IsolationLevel;
use Polidog\Tehilim\Config;
use Polidog\Tehilim\Driver\Driver;
use Polidog\Tehilim\Driver\Drivers;
use App\Tehilim\Model\User;
use App\Tehilim\Model\Post;
use App\Tehilim\Model\Tag;
use App\Tehilim\Model\Category;

final class TehilimClient extends BaseClient
{
    public readonly User $user;
    public readonly Post $post;
    public readonly Tag $tag;
    public readonly Category $category;

    public function __construct(Driver $driver)
    {
        parent::__construct($driver);
        $this->user = new User($driver);
        $this->registerModel('User', $this->user);
        $this->post = new Post($driver);
        $this->registerModel('Post', $this->post);
        $this->tag = new Tag($driver);
        $this->registerModel('Tag', $this->tag);
        $this->category = new Category($driver);
        $this->registerModel('Category', $this->category);
    }

    /**
     * Build a client from an already-configured PDO instance.
     * The driver is inferred from PDO::ATTR_DRIVER_NAME.
     */
    public static function fromPdo(PDO $pdo): self
    {
        return new self(Drivers::forPdo($pdo));
    }

    /**
     * Convenience: parse a URL into a PDO then build the client.
     * For full control over PDO attributes, use fromPdo() instead.
     */
    public static function fromUrl(string $url, ?string $user = null, ?string $password = null): self
    {
        return self::fromPdo(Config::pdo($url, $user, $password));
    }

    /**
     * @template T
     * @param callable(self): T $fn
     * @param ?IsolationLevel $isolation isolation level for the top-level
     *        transaction (driver-dependent); must be null on nested calls.
     * @return T|mixed
     */
    public function transaction(callable $fn, ?IsolationLevel $isolation = null): mixed
    {
        return parent::transaction($fn, $isolation);
    }
}
