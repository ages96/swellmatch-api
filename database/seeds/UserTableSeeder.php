<?php

use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name' => 'Ages Mugnia',
            'email' => 'agestestingadmin@gmail.com',
            'password' => app('hash')->make('admin123'),
            'remember_token' => str_random(10),
        ]);
    }
}
