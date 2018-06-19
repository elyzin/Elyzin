<div class="row middle-xs">
    <div class="col-xs"></div>
	<div class='col-xs entrance'>
	  <div class='enthead'><br/><br/>
	    <div id='logo'>
	      <span id='logo_main'>{{name}}</span>
	      <span id='logo_line'></span>
	      <span id='logo_trail'>{{caption}}</span>
	    </div><br/><br/>
		</div>
		<div class='grid entmid shade'>
	    <H3>{{headline}}</H3>
		<form action='account/{{act}}' method='post' id='login_form'>
	      <input name="act" type="hidden" value="{{act}}"/>
	      <input name="wait" type="hidden" value="{{wait}}"/>
	      {{fields}}
	      <button type="submit" class='button{{button_class}}' autofocus="autofocus" {{button_state}}><i class="fa fa-{{button_icon}}" aria-hidden="true"></i> {{button_text}}</button>
	  </form>
	  </div>
	  <div class='entfoot'>  
	    &copy;  {{copyright}}
	  </div>
	</div>  
    <div class="col-xs"></div>
</div>
{{script}}