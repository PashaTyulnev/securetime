<?php

namespace App\Twig\Components\Company;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'CompanyList',
    template: 'company/company_list.html.twig'
)]
class CompanyList
{
    public string $message;
}
