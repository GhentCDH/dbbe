import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Params, Router } from '@angular/router';
import { Observable } from 'rxjs';
import { AdminService } from '../admin.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent implements OnInit {
  username:string;
  password:string;

  constructor(private route: ActivatedRoute, private router: Router, private adminService: AdminService) { }

  ngOnInit() {
  }

  doLogin(username:string, password:string) {
    console.log(username+" "+password);
    this.adminService.login(username, password).subscribe(val=>{
      console.log(val);
      console.log('Logged in: ?');
      if(val.success==true) {
          console.log('logged in!');
          //redirect to the main page
          this.router.navigate(["/lister"]);
      } else {
        console.log('could not log in...');
        //TODO add some error message here
      }
    });
  }
}
