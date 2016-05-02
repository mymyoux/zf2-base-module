<?php
namespace Core\Queue\Listener;

use Core\Queue\ListenerAbstract;
use Core\Queue\ListenerInterface;

use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class Fullcontact extends ListenerAbstract implements ListenerInterface
{

    protected $queueName;
    private $tries;
    private $queue;

    /**
     * @param int $tries
     */
    public function __construct($tries = 3)
    {
    }

    public function checkJob( $data )
    {
        return true;
    }

    public function executeJob( $data )
    {
        $this->fullcontact  = $this->sm->get('ApiManager')->get( 'fullcontact' );

        $json   = json_encode($data);
        var_dump($json);

        foreach ($data['employees'] as $employee)
        {
            $name       = mb_strtolower($employee['name']);
            $first_name = mb_substr($name, 0, mb_strpos($name, ' '));
            $last_name  = mb_substr($name, mb_strpos($name, ' ') + 1);

            var_dump($first_name);
            var_dump($last_name);

            $test = 1;
            while (true === method_exists($this, 'test' . $test))
            {
                $email = $this->{ 'test' . $test }($first_name, $last_name) . '@typeform.com';

                var_dump($email);
                $data = $this->fullcontact->get('person', ['email' => $email]);

                var_dump($data);
                sleep(1);
                // dd($data);
                $test++;
            }
        }
    }

    /**
     * paul.dupont@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test1($first_name, $last_name)
    {
        return $first_name . '.' . $last_name;
    }

    /**
     * dupont.paul@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test2($first_name, $last_name)
    {
        return $last_name . '.' . $first_name;
    }

    /**
     * p.dupont@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test3($first_name, $last_name)
    {
        return mb_substr($first_name, 0, 1) . '.' . $last_name;
    }

    /**
     * pdupont@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test4($first_name, $last_name)
    {
        return mb_substr($first_name, 0, 1) . $last_name;
    }

    /**
     * pauldupont@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test5($first_name, $last_name)
    {
        return $first_name . $last_name;
    }

    /**
     * dupontpaul@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test6($first_name, $last_name)
    {
        return $last_name . $first_name;
    }

    /**
     * dupont@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test7($first_name, $last_name)
    {
        return $last_name;
    }

     /**
     * paul@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test8($first_name, $last_name)
    {
        return $first_name;
    }

    /**
     * paul@entreprise.fr
     *
     * @param  string $first_name first name of the employee
     * @param  string $last_name  last name of the employee
     * @return string             email format
     */
    private function test9($first_name, $last_name)
    {
        return mb_substr($first_name, 0, 1) . mb_substr($last_name, 0, 1);
    }
}
