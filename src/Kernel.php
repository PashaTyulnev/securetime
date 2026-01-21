<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function boot(): void
    {
        parent::boot();
        
        // Zeitzone für PHP setzen (Europe/Berlin für Deutschland)
        date_default_timezone_set('Europe/Berlin');
    }
}
