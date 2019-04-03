import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Routes }   from '@angular/router';
import {SelectorComponent} from '../selector/selector.component';
import {ListerComponent} from '../lister/lister.component';

const appRoutes: Routes = [
  { path: 'selector/:idpoem', component: SelectorComponent },
  { path: 'lister', component: ListerComponent },
  { path: '',   redirectTo: '/lister', pathMatch: 'full' },
];


@NgModule({
  imports: [
    CommonModule,
    RouterModule.forRoot(appRoutes)
  ],
  declarations: [
    
  ],
  exports: [ RouterModule ]
})
export class AppRoutingModule { }
