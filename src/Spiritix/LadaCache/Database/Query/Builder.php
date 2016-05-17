<?php
/**
 * This file is part of the spiritix/lada-cache package.
 *
 * @copyright Copyright (c) Matthias Isler <mi@matthias-isler.ch>
 * @license   MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Spiritix\LadaCache\Database\Query;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Spiritix\LadaCache\QueryHandler;

/**
 * Overrides Laravel's query builder class.
 *
 * @package Spiritix\LadaCache\Database
 * @author  Matthias Isler <mi@matthias-isler.ch>
 */
class Builder extends \Illuminate\Database\Query\Builder
{
    /**
     * Handler instance.
     *
     * @var QueryHandler
     */
    private $handler;

    /**
     * The key that should be used when caching the query.
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * The number of seconds to cache the query.
     *
     * @var int
     */
    protected $cacheSeconds;

    /**
     * Create a new query builder instance.
     *
     * @param  ConnectionInterface $connection
     * @param  Grammar             $grammar
     * @param  Processor           $processor
     * @param  QueryHandler        $handler
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar, Processor $processor, QueryHandler $handler)
    {
        parent::__construct($connection, $grammar, $processor);

        $this->handler = $handler;
        $this->cacheSeconds = (int) config('lada-cache.expiration-time', 3600);
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new static($this->connection, $this->grammar, $this->processor, $this->handler);
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        return $this->handler->setBuilder($this)->cacheQuery($this->cacheSeconds, $this->cacheKey, function() {
            return parent::runSelect();
        });
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array $values
     *
     * @return bool
     */
    public function insert(array $values)
    {
        $result = parent::insert($values);

        $this->handler->setBuilder($this)->invalidateQuery('insert');

        return $result;
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  string $sequence
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $result = parent::insertGetId($values, $sequence);

        $this->handler->setBuilder($this)->invalidateQuery('insertGetId');

        return $result;
    }

    /**
     * Update a record in the database.
     *
     * @param  array $values
     *
     * @return int
     */
    public function update(array $values)
    {
        $result = parent::update($values);

        $this->handler->setBuilder($this)->invalidateQuery('update');

        return $result;
    }

    /**
     * Delete a record from the database.
     *
     * @param  null|int $id
     *
     * @return int
     */
    public function delete($id = null)
    {
        $result = parent::delete($id);

        $this->handler->setBuilder($this)->invalidateQuery('delete');

        return $result;
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate()
    {
        parent::truncate();

        $this->handler->setBuilder($this)->invalidateQuery('truncate');
    }

    /**
     * Indicate that the query results should be cached.
     *
     * @param  \DateTime|int $seconds
     * @param  string  $key
     * @return $this
     */
    public function remember($seconds, $key = null)
    {
        list($this->cacheSeconds, $this->cacheKey) = [$seconds, $key];
        return $this;
    }

    /**
     * Indicate that the query results should be cached forever.
     *
     * @param  string  $key
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function rememberForever($key = null)
    {
        return $this->remember($this->cacheSeconds, $key);
    }
}
