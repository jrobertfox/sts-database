<?php
namespace STS\Core\Api;
interface SchoolFacade
{
    public function getSchoolsForSpecification($spec);
    
    public function getSchoolTypes();
}
