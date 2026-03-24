<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Province;
use Illuminate\Database\Seeder;

class MozambiqueLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'Maputo' => [
                'code' => 'MPM',
                'cities' => [
                    'Maputo Cidade' => [
                        'KaMpfumo', 'KaNhlamankulu', 'KaMaxaquene', 'KaMavota',
                        'KaMubukwana', 'KaTembe', 'KaNyaka', 'Polana Caniço',
                        'Alto Maé', 'Sommerschield', 'Bairro Central', 'Malanga',
                        'Xipamanine', 'Urbanização', 'Malhazine', 'Bagamoyo',
                    ],
                    'Matola' => [
                        'Matola A', 'Matola B', 'Matola C', 'Matola D',
                        'Matola Rio', 'Machava', 'Infulene', 'Liberdade',
                        'Mucofene', 'Patrice Lumumba', 'Fomento', 'Jardim',
                    ],
                    'Boane' => ['Boane Sede', 'Sabie', 'Belavista'],
                    'Marracuene' => ['Marracuene Sede', 'Machubo', 'Nhapanguene'],
                    'Moamba' => ['Moamba Sede', 'Pessene'],
                ],
            ],
            'Gaza' => [
                'code' => 'GZA',
                'cities' => [
                    'Xai-Xai' => [
                        'Xai-Xai Cidade', 'Inhamissa', 'Marien Ngouabi',
                        'Patrice Lumumba', 'Inharrime', 'Zongoene',
                    ],
                    'Chókwè' => ['Chókwè Sede', 'Lionde'],
                    'Macia' => ['Macia Sede', 'Calanga'],
                    'Chibuto' => ['Chibuto Sede'],
                    'Mandlakazi' => ['Mandlakazi Sede'],
                ],
            ],
            'Inhambane' => [
                'code' => 'INH',
                'cities' => [
                    'Inhambane Cidade' => [
                        'Inhambane Sede', 'Balane', 'Muelé', 'Chambone',
                    ],
                    'Maxixe' => ['Maxixe Sede', 'Chicuque'],
                    'Vilankulo' => ['Vilankulo Sede', 'Chibuene'],
                    'Tofo' => ['Praia do Tofo'],
                    'Massinga' => ['Massinga Sede'],
                    'Homoine' => ['Homoine Sede'],
                ],
            ],
            'Sofala' => [
                'code' => 'SOF',
                'cities' => [
                    'Beira' => [
                        'Ponta Gea', 'Macúti', 'Munhava', 'Manga',
                        'Chaimite', 'Esturro', 'Palmeiras', 'Inhamízua',
                        'Matacuane', 'Buzi', 'Macuti Praia', 'Maquinino',
                    ],
                    'Dondo' => ['Dondo Sede', 'Muda'],
                    'Gorongosa' => ['Gorongosa Sede'],
                    'Chibabava' => ['Chibabava Sede'],
                    'Buzi' => ['Buzi Sede'],
                ],
            ],
            'Manica' => [
                'code' => 'MAN',
                'cities' => [
                    'Chimoio' => [
                        'Chimoio Sede', 'Sagrada Família', 'Eduardo Mondlane',
                        'Chicharro', 'Nhamaonha', 'Aeroporto', 'Songo',
                    ],
                    'Manica' => ['Manica Sede', 'Machipanda'],
                    'Gondola' => ['Gondola Sede'],
                    'Báruè' => ['Catandica'],
                ],
            ],
            'Tete' => [
                'code' => 'TET',
                'cities' => [
                    'Tete Cidade' => [
                        'Tete Sede', 'Matundo', 'Manyanga', 'Benga', 'Chingodzi',
                    ],
                    'Moatize' => ['Moatize Sede', 'Benga'],
                    'Cahora Bassa' => ['Songo'],
                    'Changara' => ['Changara Sede'],
                ],
            ],
            'Zambézia' => [
                'code' => 'ZAM',
                'cities' => [
                    'Quelimane' => [
                        'Quelimane Sede', 'Coalane', 'Chabeco', 'Sangalaza',
                        'Estação', 'Madal', '3 de Fevereiro', 'Eduardo Mondlane',
                    ],
                    'Mocuba' => ['Mocuba Sede'],
                    'Gurúè' => ['Gurúè Sede', 'Lioma'],
                    'Alto Molócuè' => ['Alto Molócuè Sede'],
                    'Milange' => ['Milange Sede'],
                ],
            ],
            'Nampula' => [
                'code' => 'NAM',
                'cities' => [
                    'Nampula Cidade' => [
                        'Namicopo', 'Muatala', 'Natikiri', 'Muhala',
                        'Napipine', 'Cariacó', 'Anchilo', 'Marrere',
                        'Nkho', 'Mutauanha',
                    ],
                    'Nacala' => ['Nacala Porto', 'Nacala-a-Velha'],
                    'Angoche' => ['Angoche Sede'],
                    'Ilha de Moçambique' => ['Ilha de Moçambique Sede'],
                    'Monapo' => ['Monapo Sede'],
                    'Ribáuè' => ['Ribáuè Sede'],
                ],
            ],
            'Cabo Delgado' => [
                'code' => 'CAD',
                'cities' => [
                    'Pemba' => [
                        'Pemba Sede', 'Paquitequete', 'Wimbe', 'Maringanha',
                        'Ingonane', 'Cariaco',
                    ],
                    'Montepuez' => ['Montepuez Sede'],
                    'Mocímboa da Praia' => ['Mocímboa da Praia Sede'],
                    'Mueda' => ['Mueda Sede'],
                    'Chiúre' => ['Chiúre Sede'],
                ],
            ],
            'Niassa' => [
                'code' => 'NIA',
                'cities' => [
                    'Lichinga' => [
                        'Lichinga Sede', 'Namacha', 'Fatima', 'Eduardo Mondlane',
                    ],
                    'Cuamba' => ['Cuamba Sede'],
                    'Mandimba' => ['Mandimba Sede'],
                    'Sanga' => ['Sanga Sede'],
                ],
            ],
            'Maputo Provincia' => [
                'code' => 'MPC',
                'cities' => [
                    'Manhiça' => ['Manhiça Sede', 'Xinavane'],
                    'Namaacha' => ['Namaacha Sede'],
                    'Magude' => ['Magude Sede'],
                ],
            ],
        ];

        foreach ($locations as $provinceName => $data) {
            $province = Province::create([
                'name' => $provinceName,
                'code' => $data['code'],
            ]);

            foreach ($data['cities'] as $cityName => $neighborhoods) {
                $city = City::create([
                    'province_id' => $province->id,
                    'name' => $cityName,
                ]);

                foreach ($neighborhoods as $neighborhoodName) {
                    Neighborhood::create([
                        'city_id' => $city->id,
                        'name' => $neighborhoodName,
                    ]);
                }
            }
        }
    }
}
