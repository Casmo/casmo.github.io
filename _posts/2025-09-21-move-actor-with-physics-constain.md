---
layout: post
title: Move an actor in Unreal with Physics Constrain
---

In one of our games we have a physics object (a ball) that can only move on the XY-axis. It should not move on the Z. The most simple way to do this is the constrain the movement of Z in the mesh physics property.

<!--break-->

Now we had an issue that when the object changed size the actor whould get stuck into the floor. Here are some problems and possible solution you can use in the Unreal Engine:

**Remove the constrain**
One solution would be to remove the constrain and in tick() always set the Z on a fixed value.
Problem: This would run into the problem that tick and physics are two seperated loops. Meaning that the position changed on the actor might be overriden by the physics tick.
 
 **Change pivot**
 We may change the pivot to the bottom of the actor. This is good solution since changing the actor's size will move the ball 'upwards' and not through the floor.
 Problem: We are attaching effects and niagara particles on the actor. These will need to be adjusted because now they are attached on the bottom of the actor instead of in the center.

 **Temporary remove the constrain**
 We tried to remove the constrained, set the new Z position and then enable the constrain back after moving.
 Problem: This didn't work. We could not remove the constrain in Blueprint (there is no node for it) and probably we would have run in the same issue as removing the constain all together.

 **Use constain mode**
 Unreal also provides a constrain mode. There are a few option to choise from but for our purpose we need to use "Mode XY". When we wanted to move the position we just set the mode to "None", changed the position and then changed the mode by to "XY". This did even work in tick!

 **Best solution**
 The best solution would probably using the PhysicsConstraintComponent. This component can be adjusted easily through blueprint with a lot of options.

 Good luck!
