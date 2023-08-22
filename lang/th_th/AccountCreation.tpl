<p>{$To},</p>

<p>ผู้ใช้ใหม่ได้ลงทะเบียนด้วยข้อมูลต่อไปนี้:<br/>
อีเมล: {$EmailAddress}<br/>
ชื่อ: {$FullName}<br/>
โทรศัพท์: {$Phone}<br/>
องค์กร: {$Organization}<br/>
ตำแหน่ง: {$ตPosition}</p>
{if !empty($CreatedBy)}
    สร้างโดย: {$CreatedBy}
{/if}
