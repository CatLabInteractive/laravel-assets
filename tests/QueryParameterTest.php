<?php

use CatLab\Assets\Laravel\Controllers\AssetController;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Chrome's address bar autocompletes ?borderWidth=2 to a ?borderwidth=2
 * it has in history, and the lowercase parameter was ignored.
 */
class QueryParameterTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    private function queryParameter(array $query, $name, $default = null)
    {
        if (!class_exists('Request')) {
            class_alias(\Illuminate\Support\Facades\Request::class, 'Request');
        }

        $app = new Container();
        $app->instance('request', Request::create('/assets/abc', 'GET', $query));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $method = new \ReflectionMethod(AssetController::class, 'getQueryParameter');
        return $method->invoke(new AssetController(), $name, $default);
    }

    public function testReadsTheCamelCaseName()
    {
        $this->assertSame('2', $this->queryParameter([ 'borderWidth' => '2' ], AssetController::QUERY_PARAM_BORDER_WIDTH));
    }

    public function testFallsBackToTheLowercaseName()
    {
        $this->assertSame('2', $this->queryParameter([ 'borderwidth' => '2' ], AssetController::QUERY_PARAM_BORDER_WIDTH));
        $this->assertSame('ff0000', $this->queryParameter([ 'bordercolor' => 'ff0000' ], AssetController::QUERY_PARAM_BORDER_COLOR));
    }

    public function testPrefersTheCamelCaseNameWhenBothAreGiven()
    {
        $this->assertSame('2', $this->queryParameter([ 'borderWidth' => '2', 'borderwidth' => '5' ], AssetController::QUERY_PARAM_BORDER_WIDTH));
    }

    public function testReturnsTheDefaultWhenNeitherIsGiven()
    {
        $this->assertNull($this->queryParameter([], AssetController::QUERY_PARAM_BORDER_WIDTH));
        $this->assertSame(3, $this->queryParameter([], AssetController::QUERY_PARAM_BORDER_WIDTH, 3));
    }
}
