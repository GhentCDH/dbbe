import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Params, Router } from '@angular/router';
import { Observable } from 'rxjs';
import { AdminService } from '../admin.service';
import { ViewChild } from '@angular/core';

@Component({
  selector: 'app-selector',
  templateUrl: './selector.component.html',
  styleUrls: ['./selector.component.css']
})
export class SelectorComponent implements OnInit {

  originalPoem: any = {};
  selectedText: string = null;
  startIndex: number = null;
  endIndex: number = null;
  substringAnnotations = [];
  poemAnnotation: any = {};
  @ViewChild('poemtext') poemTextElement;

  constructor(private route: ActivatedRoute, private adminService: AdminService, private router:Router) { }

  ngOnInit() {
    this.route.params.forEach((params: Params) => {

      let id = params['idpoem'];
      console.log('got this id for original poem: ' + id);

      let originalPoem$: Observable<any[]> = this.adminService.getOriginalPoem(id);
      originalPoem$.subscribe(val => {
        console.log(val);
        if (val.length == 1) {
          this.originalPoem = val[0];
          console.log(this.originalPoem);
          /**
                * Get any annotations that were already made.
                */
          this.refreshSubstringAnnotations();
          this.refreshPoemAnnotation();
        }
      });


    });
  }

  clearSelection() {
    this.selectedText = null;
    this.startIndex = null;
    this.endIndex = null;
  }

  validSelectionPresent() {
    return this.selectedText != null && this.selectedText.length > 0;
  }

  shortVersionOfSelectedText() {
    if (this.selectedText == null)
      return null;
    let result: string = "";
    let sublength: number = 15;

    let endA = Math.min(sublength, this.selectedText.length);
    let startB = this.selectedText.length - sublength;
    //modify B's start -> can't go further back than the end of A
    startB = Math.max(startB, endA);

    result += this.selectedText.substring(0, endA);
    if (result.length < this.selectedText.length) {
      result += "..." + this.selectedText.substring(startB);
    }
    return result;
  }

  refreshSubstringAnnotations() {
    let substringAnnotations$ = this.adminService.getSubstringAnnotations(this.originalPoem.id);
    substringAnnotations$.subscribe(val => {
      this.substringAnnotations = val;
    });
  }

  refreshPoemAnnotation() {
    this.adminService.getPoemAnnotation(this.originalPoem.id).subscribe(val => {
      if (val.length == 1) {
        this.poemAnnotation = val[0];
      } else {
        this.poemAnnotation = null;
      }
    }
    );
  }

  deleteAnnotation(annotation: any) {
    this.adminService.deleteAnnotation(annotation).subscribe(val => {
      this.refreshSubstringAnnotations();
    });
  }

  updateSelection() {
    let selection = window.getSelection();
    let range = window.getSelection().getRangeAt(0);


    let startOfSelectionNode = selection.anchorNode;
    let theActualPoemTextNode = this.poemTextElement.nativeElement.childNodes[0];


    //first of all, check if we're having a selection within the right element
    let startOK = theActualPoemTextNode == range.startContainer;
    let endOK = theActualPoemTextNode == range.endContainer;
    if (!startOK || !endOK) {
      this.clearSelection();
      return;
    }

    //check if we have an actual range
    if (range.startOffset == range.endOffset) {
      this.clearSelection();
      return;
    }

    //proceed, we have a valid selection!
    this.selectedText = selection.toString();
    this.startIndex = range.startOffset;
    this.endIndex = range.endOffset;
  }


  setCaesura(bNr: number): void {
    this.setSubstringAnnotation('caesura', 'B' + bNr);
    //just post it to the webservice, let it handle any collisions and potential updates needed
  }

  isCaesuraEnabled(bNr: number): boolean {
    return this.isSubstringAnnotationPresent('caesura', 'B' + bNr);
  }

  setNewClause(b: boolean): void {
    this.setSubstringAnnotation('newclause', b + "");
  }

  isNewClauseEnabled(b: boolean): boolean {
    return this.isSubstringAnnotationPresent('newclause', b + "");
  }

  setP2(s: string): void {
    if (s != 'on' && s != 'else') {
      console.log('invalid option for p2');
      return;
    }
    this.setSubstringAnnotation('p2', s);
  }

  isP2(s: string): boolean {
    return this.isSubstringAnnotationPresent('p2', s);
  }

  setApostrophe(s: string): void {
    if (s != 'independent' && s != 'partofcolon' && s != 'spreadovercola') {
      console.log('invalid option for apostrophe.');
      return;
    }
    this.setSubstringAnnotation('apostrophe', s);
  }

  isApostropheSet(s: string): boolean {
    return this.isSubstringAnnotationPresent('apostrophe', s);
  }

  setEnjambment(b: string): void {
    if (b != 'cesuur' && b != 'vers') {
      console.log('invalid option for enjambment set.');
      return;
    }
    this.setSubstringAnnotation('enjambment', b);
  }

  isEnjambmentSet(b: string): boolean {
    return this.isSubstringAnnotationPresent('enjambment', b);
  }

  setApposition(s: string): boolean {
    if(s!='independentcolon' && s!='partofcolon' && s!='spreadovercola') {
      console.log('invalid option for apposition');
      return;
    }
    this.setSubstringAnnotation('apposition', s);
  }

  isAppositionSet(s: string) {
    return this.isSubstringAnnotationPresent('apposition', s);
  }

  setClitics(s: string) : boolean {
    if(s!='p2' && s!='pre-verbal' && s!='post-verbal') {
      console.log('invalid option for clitics');
      return;
    }
    this.setSubstringAnnotation('clitics', s);
  }

  isCliticsSet(s: string) {
    return this.isSubstringAnnotationPresent('clitics', s);
  }

  setProsodyCorrect(b: boolean): void {
    //bonus points: if it's already set to b, remove the annotation (toggle functionality)
    if (this.poemAnnotation!=null && this.poemAnnotation.prosodycorrect == b) {
      this.adminService.setPoemAnnotation(this.originalPoem.id, 'prosodycorrect', null).subscribe(val => this.refreshPoemAnnotation());
    } else {
      this.adminService.setPoemAnnotation(this.originalPoem.id, 'prosodycorrect', b + "").subscribe(val => this.refreshPoemAnnotation());
    }
  }

  isProsodyCorrect(b: boolean): boolean {
    return this.poemAnnotation!=null && this.poemAnnotation.prosodycorrect!=null && this.poemAnnotation.prosodycorrect == b;
  }

  logout():void {
    this.adminService.logout().subscribe(val=>{
      this.router.navigate(["/login"]);
    });
  }




  isSubstringAnnotationPresent(key: string, value: string): boolean {
    //loop over the current annotations
    for (let i = 0; i < this.substringAnnotations.length; i++) {
      let sa = this.substringAnnotations[i];
      // console.log(this.substringAnnotations[i]);
      // console.log(key+";"+value);
      // console.log(sa.startIndex==this.startIndex);
      // console.log(sa.endIndex==this.endIndex);
      // console.log(sa.key==key);
      // console.log(sa.value == value);
      if (sa.startindex == this.startIndex && sa.endindex == this.endIndex && sa.key == key && sa.value == value) {
        //found it!
        return true;
      }
    }
    return false;
  }

  setSubstringAnnotation(key: string, value: string): void {
    let result$ = this.adminService.setSubstringAnnotation(this.startIndex, this.endIndex, this.originalPoem.id, this.selectedText, key, value);
    result$.subscribe(val => {
      this.refreshSubstringAnnotations();
    });
  }


}
