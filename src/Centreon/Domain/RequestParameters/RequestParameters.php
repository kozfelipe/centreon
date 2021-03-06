<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */
declare(strict_types=1);

namespace Centreon\Domain\RequestParameters;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;

class RequestParameters implements RequestParametersInterface
{
    const NAME_FOR_LIMIT = 'limit';
    const NAME_FOR_PAGE = 'page';
    const NAME_FOR_SEARCH = 'search';
    const NAME_FOR_SORT = 'sort_by';
    const NAME_FOR_TOTAL = 'total';

    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';
    const DEFAULT_ORDER = self::ORDER_ASC;

    const DEFAULT_LIMIT = 10;
    const DEFAULT_PAGE = 1;
    const DEFAULT_SEARCH_OPERATOR = self::OPERATOR_EQUAL;

    const OPERATOR_EQUAL = '$eq';
    const OPERATOR_NOT_EQUAL = '$neq';
    const OPERATOR_LESS_THAN = '$lt';
    const OPERATOR_LESS_THAN_OR_EQUAL = '$le';
    const OPERATOR_GREATER_THAN = '$gt';
    const OPERATOR_GREATER_THAN_OR_EQUAL = '$ge';
    const OPERATOR_LIKE = '$lk';
    const OPERATOR_NOT_LIKE = '$nk';
    const OPERATOR_IN = '$in';
    const OPERATOR_NOT_IN = '$ni';

    const AGGREGATE_OPERATOR_OR = '$or';
    const AGGREGATE_OPERATOR_AND = '$and';
    const CONCORDANCE_MODE_STRICT = 1;
    const CONCORDANCE_MODE_NO_STRICT = 0;

    private $authorizedOrders = [self::ORDER_ASC, self::ORDER_DESC];

    private $extraParameters = [];

    /**
     * @var int Indicates whether we should consider only known search parameters.
     * Used in the data repository classes.
     */
    private $concordanceStrictMode = self::CONCORDANCE_MODE_NO_STRICT;

    /**
     * @var array Array representing fields to search for
     */
    private $search = [];

    /**
     * @var array Field to order
     */
    private $sort = [];

    /**
     * @var int Number of the page
     */
    private $page = 1;

    /**
     * @var int Number of records per page
     */
    private $limit = 10;

    /**
     * @var int Total of lines founds without limit
     */
    private $total = 0;

    /**
     * @inheritDoc
     */
    public function addExtraParameter(string $parameterName, $value): void
    {
        $this->extraParameters[$parameterName] = $value;
    }

    /**
     * @return int
     */
    public function getConcordanceStrictMode(): int
    {
        return $this->concordanceStrictMode;
    }

    /**
     * @inheritDoc
     */
    public function getExtraParameter(string $parameterName)
    {
        if (array_key_exists($parameterName, $this->extraParameters)) {
            return $this->extraParameters[$parameterName];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function setConcordanceStrictMode(int $concordanceStrictMode): void
    {
        $this->concordanceStrictMode = $concordanceStrictMode;
    }

    /**
     * @inheritDoc
     */
    public function findSearchParameter(string $keyToFind, array $parameters): ?string
    {
        foreach ($parameters as $key => $value) {
            if ($key === $keyToFind) {
                if (is_object($value)) {
                    $value = (array)$value;
                    return $value[key($value)];
                } else {
                    return $value;
                }
            } else {
                if (is_array($value) || is_object($value)) {
                    $value = (array) $value;
                    if (($value = $this->findSearchParameter($keyToFind, $value)) !== null) {
                        return $value;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Try to fix the schema in case of bad structure
     *
     * @throws \Exception
     */
    private function fixSchema()
    {
        $search = $this->search;

        if (!empty($search)) {
            if ((
                    !isset($search[RequestParameters::AGGREGATE_OPERATOR_AND])
                    && !isset($search[RequestParameters::AGGREGATE_OPERATOR_OR])
                )
                || (count($this->search) > 1)
            ) {
                $newSearch[RequestParameters::AGGREGATE_OPERATOR_AND] = $search;
                $this->search = $newSearch;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function isSearchParameterDefined(string $parameter): bool
    {
        return $this->findSearchParameter($parameter, (array) $this->getSearch()) !== null;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            self::NAME_FOR_PAGE => $this->page,
            self::NAME_FOR_LIMIT => $this->limit,
            self::NAME_FOR_SEARCH => !empty($this->search)
                ? json_decode(json_encode($this->search), true)
                : new \stdClass,
            self::NAME_FOR_SORT => !empty($this->sort)
                ? json_decode(json_encode($this->sort), true)
                : new \stdClass,
            self::NAME_FOR_TOTAL => $this->total
        ];
    }

    /**
     * @inheritDoc
     */
    public function unsetSearchParameter(string $parameterToExtract)
    {
        $parameters = $this->search;
        $extractFunction = null;
        $extractFunction = function (string $parameterToExtract, &$parameters) use (&$extractFunction) {
            foreach ($parameters as $key => &$value) {
                if ($key === $parameterToExtract) {
                    unset($parameters[$key]);
                } elseif (is_array($value) || is_object($value)) {
                    $value = (array)$value;
                    $extractFunction($parameterToExtract, $value);
                }
            }
        };
        $extractFunction($parameterToExtract, $parameters);
        $this->search = $parameters;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @inheritDoc
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @inheritDoc
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     * @inheritDoc
     */
    public function getSearch(): array
    {
        return $this->search;
    }

    /**
     * @inheritDoc
     */
    public function setSearch(string $search): void
    {
        $search = json_decode($search ?? '{}');
        $this->search = (array) $search;
        $this->fixSchema();
    }

    /**
     * @inheritDoc
     */
    public function getSort(): array
    {
        return $this->sort;
    }

    /**
     * @inheritDoc
     */
    public function setSort(string $sortRequest): void
    {
        $sortRequestToAnalyze = json_decode($sortRequest ?? self::DEFAULT_SEARCH, true);
        if (!is_array($sortRequestToAnalyze)) {
            if ($sortRequest[0] != '{') {
                $this->sort = [$sortRequest => self::DEFAULT_ORDER];
            } else {
                throw new \RestBadRequestException("Bad format for the sort request parameter");
            }
        } elseif (is_array($sortRequestToAnalyze)) {
            foreach ($sortRequestToAnalyze as $name => $order) {
                $isMatched = preg_match(
                    '/^([a-zA-Z0-9_.-]*)$/i',
                    $name,
                    $sortFound,
                    PREG_OFFSET_CAPTURE
                );
                if (!$isMatched || !in_array(strtoupper($order), $this->authorizedOrders)) {
                    unset($sortRequestToAnalyze[$name]);
                } else {
                    $sortRequestToAnalyze[$name] = strtoupper($order);
                }
            }
            $this->sort = $sortRequestToAnalyze;
        }
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @inheritDoc
     */
    public function setTotal(int $total): void
    {
        $this->total = $total;
    }
}
