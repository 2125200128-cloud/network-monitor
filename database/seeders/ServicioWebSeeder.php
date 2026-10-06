<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServicioWeb;

class ServicioWebSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $urls = [
            [
                'nombre' => 'Gob.mx - Acta de Nacimiento',
                'url' => 'https://www.gob.mx/ActaNacimiento',
                'categoria' => 'Gobierno',
            ],
            [
                'nombre' => 'Adeudos Vehiculares Jalisco',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/serviciosVehiculares/adeudos',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Sefin - Citas Agenda',
                'url' => 'https://sefinenlinea.jalisco.gob.mx/citas/Agenda.aspx',
                'categoria' => 'Sefin',
            ],
            [
                'nombre' => 'Sefin - Agenda Recaudadora',
                'url' => 'https://sefinenlinea.jalisco.gob.mx/agendarecaudadora/agenda.aspx',
                'categoria' => 'Sefin',
            ],
            [
                'nombre' => '3Clic - Asignación',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/3clic/asignacion.jsp',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Bancos Reimprime (10.4.150.205)',
                'url' => 'http://10.4.150.205:8080/bancos/Reimprime',
                'categoria' => 'Bancos',
            ],
            [
                'nombre' => 'Bancos Reimprime (10.4.150.206)',
                'url' => 'http://10.4.150.206:8080/bancos/Reimprime',
                'categoria' => 'Bancos',
            ],
            [
                'nombre' => 'Bancos Reimprime (10.4.150.207)',
                'url' => 'http://10.4.150.207:8080/bancos/Reimprime',
                'categoria' => 'Bancos',
            ],
            [
                'nombre' => 'Comunicación Social Jalisco',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/comsocial/Comsocial',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Facturación Hacienda Jalisco',
                'url' => 'https://facturacionhacienda.jalisco.gob.mx/inicio',
                'categoria' => 'Sefin',
            ],
            [
                'nombre' => '3Clic - Gasto',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/3clic/gasto.jsp',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => '3Clic - Ingreso',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/3clic/ingreso.jsp',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'HTTP Servidor Interno (10.4.1.206)',
                'url' => 'http://10.4.1.206/http/index.html',
                'categoria' => 'Internos',
            ],
            [
                'nombre' => 'HTTP Servidor Interno (10.4.1.207)',
                'url' => 'http://10.4.1.207/http/index.html',
                'categoria' => 'Internos',
            ],
            [
                'nombre' => 'Nómina Jalisco',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/nomina/Nomina',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Refrendo Placas Jalisco',
                'url' => 'https://refrendo.jalisco.gob.mx/#placas',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Presupuesto Jalisco',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/presupuesto/Presupuesto',
                'categoria' => 'Trámites',
            ],
            [
                'nombre' => 'Multipagos Reimprime (Público)',
                'url' => 'https://gobiernoenlinea1.jalisco.gob.mx/multipagos/Reimprime',
                'categoria' => 'Multipagos',
            ],
            [
                'nombre' => 'Multipagos Reimprime (10.4.150.201)',
                'url' => 'http://10.4.150.201:8280/multipagos/Reimprime',
                'categoria' => 'Multipagos',
            ],
            [
                'nombre' => 'Multipagos Reimprime (10.4.150.202)',
                'url' => 'http://10.4.150.202:8280/multipagos/Reimprime',
                'categoria' => 'Multipagos',
            ],
            [
                'nombre' => 'Multipagos Reimprime (10.4.150.203)',
                'url' => 'http://10.4.150.203:8280/multipagos/Reimprime',
                'categoria' => 'Multipagos',
            ],
            [
                'nombre' => 'Multipagos Reimprime (10.4.150.204)',
                'url' => 'http://10.4.150.204:8280/multipagos/Reimprime',
                'categoria' => 'Multipagos',
            ],
        ];

        foreach ($urls as $item) {
            ServicioWeb::updateOrCreate(
                ['url' => $item['url']],
                [
                    'nombre' => $item['nombre'],
                    'categoria' => $item['categoria'],
                    'metodo' => 'GET',
                    'es_activo' => true
                ]
            );
        }
    }
}
