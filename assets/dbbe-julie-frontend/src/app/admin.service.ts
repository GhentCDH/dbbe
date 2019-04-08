import { Injectable } from '@angular/core';
import { Headers, Http, RequestOptions, Response } from '@angular/http';
import { Router } from '@angular/router';
import { Observable } from 'rxjs/Observable';
import 'rxjs/add/operator/map';
import 'rxjs/add/operator/catch';
import 'rxjs/add/observable/empty';
import {environment} from '../environments/environment';

@Injectable()
export class AdminService {

  baseUrl: string;
  headers: Headers;
  options: RequestOptions;

  constructor(private http: Http, private router:Router) {
    //preset the headers, they're always the same?
    this.headers = new Headers({ 'Content-Type': 'application/json' });
    this.options = new RequestOptions({ headers: this.headers, withCredentials: true });
    this.baseUrl = environment.webserviceBase;
  }


  getOriginalPoem(poemid: number): Observable<any> {
    return this.http.get(this.baseUrl + "/originalpoem/" + poemid, this.options)
      .map(val => val.json()).catch(e=>{
        console.log('going to redirect!');
        this.router.navigate(["/login"]);
        return Observable.empty();
      });
  }

  setSubstringAnnotation(startIndex:number, endIndex:number, idoriginalpoem:number, substring:string, annotationType:string, annotationValue:string): Observable<any> {
      return this.http.post(this.baseUrl+"/substringannotation/"+idoriginalpoem, {'startindex':startIndex, 'endindex':endIndex, 'substring':substring, 'key':annotationType, 'value':annotationValue},this.options )
  }

  getSubstringAnnotations(idoriginalpoem:number):Observable<any[]> {
    return this.http.get(this.baseUrl+"/substringannotation/"+idoriginalpoem,this.options).map(val=>val.json());
  }

  getPoemAnnotation(idoriginalpoem:number):Observable<any> {
    return this.http.get(this.baseUrl+"/poemannotation/"+idoriginalpoem, this.options).map(val=>val.json());
  }

  setPoemAnnotation(idoriginalpoem:number, key:string, value:string):Observable<any> {
    return this.http.put(this.baseUrl+"/poemannotation/"+idoriginalpoem, {'key':key, 'value':value}, this.options).map(val=>val.json());
  }

  deleteAnnotation(annotation:any):Observable<any> {
    return this.http.delete(this.baseUrl+"/substringannotation/"+annotation.idsubstringannotation, this.options).map(val=>val.json());
  }

  login(username:string, password:string):Observable<any> {
    return this.http.post(this.baseUrl+"/login", {"username":username, "password":password}, this.options).map(val=>val.json());
  }

  logout():Observable<any> {
    return this.http.get(this.baseUrl+"/logout", this.options).map(val=>val.json());
  }

}
