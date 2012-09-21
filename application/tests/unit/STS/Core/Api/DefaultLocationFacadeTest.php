<?php
use STS\Core\Api\DefaultLocationFacade;

class DefaultLocationFacadeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function getValidStates()
    {
        $facade = $this->getFacadeWithMockedDeps();
        $states = $facade->getStates();
        $this->assertEquals($this->getStates(), $states);
    }
    private function getStates()
    {
        return array(
                'AL' => "Alabama", 'AK' => "Alaska", 'AZ' => "Arizona", 'AR' => "Arkansas", 'CA' => "California",
                'CO' => "Colorado", 'CT' => "Connecticut", 'DE' => "Delaware", 'DC' => "District Of Columbia",
                'FL' => "Florida", 'GA' => "Georgia", 'HI' => "Hawaii", 'ID' => "Idaho", 'IL' => "Illinois",
                'IN' => "Indiana", 'IA' => "Iowa", 'KS' => "Kansas", 'KY' => "Kentucky", 'LA' => "Louisiana",
                'ME' => "Maine", 'MD' => "Maryland", 'MA' => "Massachusetts", 'MI' => "Michigan", 'MN' => "Minnesota",
                'MS' => "Mississippi", 'MO' => "Missouri", 'MT' => "Montana", 'NE' => "Nebraska", 'NV' => "Nevada",
                'NH' => "New Hampshire", 'NJ' => "New Jersey", 'NM' => "New Mexico", 'NY' => "New York",
                'NC' => "North Carolina", 'ND' => "North Dakota", 'OH' => "Ohio", 'OK' => "Oklahoma", 'OR' => "Oregon",
                'PA' => "Pennsylvania", 'RI' => "Rhode Island", 'SC' => "South Carolina", 'SD' => "South Dakota",
                'TN' => "Tennessee", 'TX' => "Texas", 'UT' => "Utah", 'VT' => "Vermont", 'VI' => 'Virgin Islands',
                'VA' => "Virginia", 'WA' => "Washington", 'WV' => "West Virginia", 'WI' => "Wisconsin",
                'WY' => "Wyoming"
        );
    }
    private function getFacadeWithMockedDeps()
    {
        $mongoDb = Mockery::mock('MongoDB');
        $facade = new DefaultLocationFacade($mongoDb);
        return $facade;
    }
}
