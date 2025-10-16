<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void {
        $rows = [
            ['name'=>'Lions','city'=>'Guatemala','logo_url'=>null],
            ['name'=>'Falcons','city'=>'Antigua','logo_url'=>null],
            ['name'=>'Sharks','city'=>'Quetzaltenango','logo_url'=>null],
            ['name'=>'Tigers','city'=>'Escuintla','logo_url'=>null],
        ];
        foreach ($rows as $r) Team::create($r);
    }
}
