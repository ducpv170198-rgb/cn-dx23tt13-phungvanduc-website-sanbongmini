<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
class CreateAdminUser extends Command {
 protected $signature='sanbong:create-admin {email}';
 protected $description='Create or promote an administrator using an interactive password prompt';
 public function handle(): int {
  $email=$this->argument('email');
  if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $this->error('Email khong hop le'); return self::FAILURE; }
  $password=$this->secret('Nhap mat khau admin (toi thieu 12 ky tu)');
  if (!is_string($password)||strlen($password)<12) { $this->error('Mat khau phai co it nhat 12 ky tu'); return self::FAILURE; }
  $user=User::firstOrNew(['email'=>$email]);
  if (!$user->exists) $user->name='Quản trị viên';
  $user->role='admin'; $user->password=Hash::make($password); $user->save();
  $this->info('Da tao/cap nhat admin: '.$email); return self::SUCCESS;
 }
}
