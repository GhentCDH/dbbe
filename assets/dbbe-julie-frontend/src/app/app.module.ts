import { BrowserModule } from '@angular/platform-browser';
import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpModule } from '@angular/http';

import { AppComponent } from './app.component';
import { SelectorComponent } from './selector/selector.component';

import { AppRoutingModule } from './app-routing/app-routing.module';

import { AdminService } from './admin.service';
import { ListerComponent } from './lister/lister.component';

@NgModule({
  declarations: [
    AppComponent,
    SelectorComponent,
    ListerComponent
  ],
  imports: [
    BrowserModule,
    FormsModule,
    HttpModule,
    AppRoutingModule
  ],
  providers: [ AdminService ],
  bootstrap: [ AppComponent ]
})
export class AppModule { }
