<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Rule;
use Livewire\Form;

class InstallationForm extends Form
{
    /**
     * Field for Account
     */
    #[Rule('required', onUpdate: false)]
    public $given_name = null;

    #[Rule('required', onUpdate: false)]
    public $family_name = null;

    #[Rule('required|email', onUpdate: false)]
    public $email = null;

    #[Rule('required|confirmed', onUpdate: false)]
    public $password = null;

    #[Rule('required', onUpdate: false)]
    public $password_confirmation = null;

    #[Rule('boolean', onUpdate: false)]
    public bool $newsletter = true;

    /**
     * Field for Database
     */
    #[Rule('required', onUpdate: false)]
    public $db_connection = 'mysql';

    #[Rule('required', onUpdate: false)]
    public $db_username = null;

    #[Rule('required', onUpdate: false)]
    public $db_password = null;

    #[Rule('required', onUpdate: false)]
    public $db_name = 'leconfe';

    #[Rule('required', onUpdate: false)]
    public $db_host = '127.0.0.1';

    #[Rule('required', onUpdate: false)]
    public $db_port = '3306';
    
    /**
     * Field for Survey
     */
    #[Rule('nullable|string|max:255', onUpdate: false)]
    public $referral_source = '';
    
    #[Rule('nullable|required_if:referral_source,other|string|max:255', onUpdate: false)]
    public $other_referral_source = '';
    
    #[Rule('array|min:0', onUpdate: false)]
    public $important_features = [];

    #[Rule('nullable|required_if:important_features,other|string|max:255', onUpdate: false)]
    public $other_important_feature = null;

    public function checkDatabaseConnection(): bool
    {
        try {
            $this->resetErrorBag('error');
            $this->reconnectDbWithNewData();
        } catch (\Throwable $th) {
            $this->addError('error', 'Connection failed: '.$th->getMessage());

            return false;
        }

        return true;
    }

    public function createDatabase(): bool
    {
        $dbName = $this->db_name;

        try {
            $this->resetErrorBag('error');
            $this->reconnectDbWithNewData();

            if (! $this->checkDatabaseExists($dbName)) {
                Schema::createDatabase($dbName);
            }
        } catch (\Throwable $th) {
            $this->addError('error', 'Create database failed: Please manually create your database '.$th->getMessage());

            return false;
        }

        return true;
    }

    private function prepareDatabaseConnection(): array
    {
        $connectionArray = config("database.connections.{$this->db_connection}", []);

        $connectionArray = array_merge($connectionArray, [
            'driver' => $this->db_connection,
            'database' => $this->db_name,
        ]);

        if (! empty($this->db_username) && ! empty($this->db_password)) {
            $connectionArray = array_merge($connectionArray, [
                'username' => $this->db_username,
                'password' => $this->db_password,
                'host' => $this->db_host,
                'port' => $this->db_port,
            ]);
        }

        return $connectionArray;
    }

    private function checkDatabaseExists($dbName): bool
    {
        return ! empty(DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'"));
    }

    protected function reconnectDbWithNewData()
    {
        $connectionArray = $this->prepareDatabaseConnection();

        Config::set("database.connections.{$this->db_connection}", $connectionArray);

        DB::purge();

        // reconnect to database with new settings
        DB::reconnect();

        DB::connection()->getPdo();
    }

    public function getHydratedData()
    {
        $data = $this->except([
            'referral_source',
            'other_referral_source',
            'important_features',
            'other_important_feature',
        ]);
        
        $data['survey_referral_source'] = $this->referral_source === 'other' ? $this->other_referral_source : $this->referral_source;

        $final_features = $this->important_features;
        if (in_array('other', $this->important_features) && !empty($this->other_important_feature)) {
            $final_features[] = $this->other_important_feature;
        }
        $final_features = array_filter($final_features, fn($feature) => $feature !== 'other');

        $data['survey_important_features'] = $final_features;

        return $data;
    }
}
